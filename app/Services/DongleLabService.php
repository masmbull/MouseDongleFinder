<?php

namespace App\Services;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Read-only "Dongle Lab" helpers for the many-dongles-on-one-hub workflow.
 *
 * Scanning labels every receiver-like device that is actually present; observing
 * diffs the live HID/mouse devices against a previous snapshot and attributes
 * new devices to dongles using Windows VID/PID and hardware-ID topology only.
 * This service never creates or updates a device match.
 */
class DongleLabService
{
    /** @var array<string, string> */
    private const VENDOR_BRANDS = [
        '046D' => 'Logitech',
        '1532' => 'Razer',
        '045E' => 'Microsoft',
        '1B1C' => 'Corsair',
        '1038' => 'SteelSeries',
        '04D9' => 'A4Tech',
        '1EA7' => 'SHARKOON',
        '0B05' => 'ASUS',
        '0951' => 'Kingston',
        '05AC' => 'Apple',
    ];

    /** @var array<string, string> */
    private const RECEIVER_FAMILIES = [
        '046D:C52B' => 'Unifying',
        '046D:C548' => 'Bolt',
        '1532:007B' => 'HyperSpeed',
    ];

    private const RECEIVER_NAME_PATTERN = '/receiver|dongle|unifying|nano|lightspeed|bolt|slipstream|hyperspeed/i';

    /** @var array{vendors: array<string, string>, products: array<string, string>}|null */
    private ?array $usbIdsMap = null;

    public function __construct(
        public UsbDeviceScanner $scanner,
        public LivePairingDetector $detector,
        private ?string $usbIdsPath = null,
    ) {}

    /**
     * Label every receiver-like device in the current USB scan.
     *
     * @return array{
     *     dongles: array<int, array<string, mixed>>,
     *     devices: array<int, array<string, mixed>>,
     *     error: string|null,
     *     scanned_at: string,
     *     message: string
     * }
     */
    public function scanDongles(): array
    {
        $scannedAt = now()->toIso8601String();

        try {
            $result = $this->scanner->scan();
        } catch (Throwable $exception) {
            Log::error('Dongle Lab USB scan failed.', ['message' => $exception->getMessage()]);
            $error = 'USB scan failed: '.$exception->getMessage();

            return [
                'dongles' => [],
                'devices' => [],
                'error' => $error,
                'scanned_at' => $scannedAt,
                'message' => 'Dongle Lab scan is unavailable: '.$error,
            ];
        }

        $scanError = $this->stringOrNull($result['error'] ?? null);
        $rawDevices = is_array($result['devices'] ?? null) ? $result['devices'] : [];
        $devices = $scanError !== null
            ? []
            : array_values(array_filter($rawDevices, 'is_array'));
        $devices = array_map(
            fn (array $device): array => $this->snapshotDevice($device, $result['scanned_at'] ?? $scannedAt),
            $devices,
        );

        if ($scanError !== null) {
            return [
                'dongles' => [],
                'devices' => [],
                'error' => $scanError,
                'scanned_at' => $result['scanned_at'] ?? $scannedAt,
                'message' => 'Dongle Lab scan is unavailable: '.$scanError,
            ];
        }

        $dongles = $this->buildDongleEntries($devices);

        return [
            'dongles' => $dongles,
            'devices' => $devices,
            'error' => null,
            'scanned_at' => $result['scanned_at'] ?? $scannedAt,
            'message' => $dongles === []
                ? 'No receiver-like devices were detected. Plug the loose dongles into the hub and scan again.'
                : sprintf(
                    'Detected %d receiver candidate(s). Label each dongle, then switch on one mouse at a time and observe which device appears.',
                    count($dongles),
                ),
        ];
    }

    /**
     * Device snapshot from the same fast scanner used by observe(), so the
     * first observation only reports devices that genuinely appeared later.
     *
     * @return array{devices: array<int, array<string, mixed>>, error: string|null}
     */
    public function baselineDevices(): array
    {
        try {
            $scan = $this->detector->scanOnly();
        } catch (Throwable $exception) {
            Log::error('Dongle Lab baseline scan failed.', ['message' => $exception->getMessage()]);

            return ['devices' => [], 'error' => $exception->getMessage()];
        }

        $error = $this->stringOrNull($scan['error'] ?? null);

        if ($error !== null) {
            return ['devices' => [], 'error' => $error];
        }

        $devices = is_array($scan['devices'] ?? null)
            ? array_values(array_filter($scan['devices'], 'is_array'))
            : [];

        return ['devices' => $devices, 'error' => null];
    }

    /**
     * Normalizes a raw live-scan device list into public mouse-like devices.
     *
     * Auto-discovery uses the same Windows PnP filter as Dongle Lab observation
     * so non-mouse interfaces and receiver shells are never attributed.
     *
     * @param  array<int, array<string, mixed>>  $devices
     * @return array<int, array<string, mixed>>
     */
    public function mouseDevices(array $devices): array
    {
        $mice = [];

        foreach ($devices as $device) {
            if (! is_array($device) || ! $this->isMouseLike($device)) {
                continue;
            }

            $mice[] = $this->publicDevice($device);
        }

        return $mice;
    }

    /**
     * Attributes one live device to every detected dongle by VID/PID or
     * hardware IDs. Shared with auto-discovery to keep a single attribution
     * implementation; a device may match several identical receivers.
     *
     * @param  array<string, mixed>  $device
     * @param  array<int, array<string, mixed>>  $dongles
     * @return array{device: array<string, mixed>, device_instance_id: string|null, candidates: array<int, array<string, mixed>>, message: string|null}
     */
    public function attributeMouse(array $device, array $dongles): array
    {
        $attributions = $this->attributeDevices($dongles, [$device], null);

        return $attributions[0] ?? [
            'device' => $device,
            'device_instance_id' => $this->stringOrNull($device['instance_id'] ?? null),
            'candidates' => [],
            'message' => null,
        ];
    }

    /**
     * Flat candidate rows for the Vue UI. The grouped `candidates` field is kept
     * for API consumers that need the associated device for each attribution.
     *
     * @param  array<int, array<string, mixed>>  $attributions
     * @param  array<int, array<string, mixed>>  $dongles
     * @return array<int, array<string, mixed>>
     */
    public function flatCandidates(array $attributions, array $dongles): array
    {
        $byKey = [];

        foreach ($dongles as $dongle) {
            if (is_array($dongle)) {
                $key = $this->stringOrNull($dongle['key'] ?? null);

                if ($key !== null) {
                    $byKey[$key] = $dongle;
                }
            }
        }

        $rows = [];

        foreach ($attributions as $attribution) {
            if (! is_array($attribution) || ! is_array($attribution['candidates'] ?? null)) {
                continue;
            }

            foreach ($attribution['candidates'] as $candidate) {
                if (! is_array($candidate)) {
                    continue;
                }

                $key = $this->stringOrNull($candidate['dongle_key'] ?? null);
                $dongle = $key !== null ? ($byKey[$key] ?? []) : [];

                $rows[] = [
                    'dongle_key' => $key,
                    'dongle_id' => $candidate['dongle_id'] ?? null,
                    'vid' => $this->normalizeId($dongle['vid'] ?? null),
                    'pid' => $this->normalizeId($dongle['pid'] ?? null),
                    'reason' => $candidate['reason'] ?? null,
                    'evidence' => $candidate['evidence'] ?? [],
                    'confidence' => $candidate['confidence'] ?? 'unknown',
                    'device' => $attribution['device'] ?? null,
                    'device_instance_id' => $attribution['device_instance_id'] ?? null,
                ];
            }
        }

        return $rows;
    }

    /**
     * Diff the live device list against a previous snapshot and attribute new
     * mouse-like devices to the labelled dongles using real Windows topology.
     *
     * @param  array<int, array<string, mixed>>  $dongles
     * @param  array<int, array<string, mixed>>  $previousDevices
     * @return array{
     *     observed_at: string,
     *     devices: array<int, array<string, mixed>>,
     *     connected_devices: array<int, array<string, mixed>>,
     *     new_devices: array<int, array<string, mixed>>,
     *     candidates: array<int, array<string, mixed>>,
     *     flat_candidates: array<int, array<string, mixed>>,
     *     error: string|null,
     *     message: string,
     *     tested_dongle_key: string|null
     * }
     */
    public function observe(array $dongles, array $previousDevices, ?string $testedDongleKey = null): array
    {
        $observedAt = now()->toIso8601String();

        try {
            $scan = $this->detector->scanOnly();
        } catch (Throwable $exception) {
            Log::error('Dongle Lab live observation failed.', ['message' => $exception->getMessage()]);
            $error = 'Live device detection failed: '.$exception->getMessage();

            return $this->errorResult($observedAt, $error, $testedDongleKey);
        }

        if (! is_array($scan)) {
            return $this->errorResult($observedAt, 'Live device detection returned an invalid response.', $testedDongleKey);
        }

        $scanError = $this->stringOrNull($scan['error'] ?? null);

        if ($scanError !== null) {
            return $this->errorResult($observedAt, $scanError, $testedDongleKey);
        }

        $currentDevices = is_array($scan['devices'] ?? null)
            ? array_values(array_filter($scan['devices'], 'is_array'))
            : [];
        $previousIds = $this->previousInstanceIds($previousDevices);
        $connectedDevices = [];
        $newDevices = [];

        foreach ($currentDevices as $device) {
            if (! $this->isMouseLike($device)) {
                continue;
            }

            $instanceId = $this->stringOrNull($device['instance_id'] ?? $device['device_instance_id'] ?? null) ?? '';
            $isNew = ! ($instanceId !== '' && in_array($instanceId, $previousIds, true));
            $publicDevice = $this->publicDevice($device);
            $publicDevice['is_new'] = $isNew;

            $connectedDevices[] = $publicDevice;

            if ($isNew) {
                $newDevices[] = $publicDevice;
            }
        }

        $candidates = $this->attributeDevices($dongles, $connectedDevices, $testedDongleKey);
        $flatCandidates = $this->flatCandidates($candidates, $dongles);
        $candidateCount = array_sum(array_map(
            fn (array $attribution): int => count(is_array($attribution['candidates'] ?? null) ? $attribution['candidates'] : []),
            $candidates,
        ));

        return [
            'observed_at' => $observedAt,
            'devices' => $currentDevices,
            'connected_devices' => $connectedDevices,
            'new_devices' => $newDevices,
            'candidates' => $candidates,
            'flat_candidates' => $flatCandidates,
            'error' => null,
            'message' => $this->observationMessage(count($connectedDevices), count($newDevices), $candidateCount, $testedDongleKey),
            'tested_dongle_key' => $testedDongleKey,
        ];
    }

    private function observationMessage(int $mouseLikeCount, int $newDeviceCount, int $candidateCount, ?string $testedDongleKey): string
    {
        if ($mouseLikeCount === 0) {
            return 'No mouse-like devices are currently connected. Switch on one mouse at a time, then observe again.';
        }

        if ($candidateCount === 0) {
            return sprintf(
                '%d mouse-like device(s) are connected, but none matched a dongle by VID/PID or hardware IDs. Select the labelled dongle you are testing and observe again.',
                $mouseLikeCount,
            );
        }

        if ($candidateCount > 1) {
            return sprintf(
                '%d candidate(s) were found. If dongles share VID/PID, choose the correct one by USB port/location and confirm with a physical pairing test.',
                $candidateCount,
            );
        }

        $hint = $testedDongleKey === null
            ? 'If the mouse was already on during the scan, select the labelled dongle you are testing and observe again.'
            : sprintf('Dongle %s is marked as the device under test; confirm with a physical pairing test.', $testedDongleKey);

        return sprintf(
            '%d mouse-like device(s) connected (%d new); %d candidate(s) found. %s',
            $mouseLikeCount,
            $newDeviceCount,
            $candidateCount,
            $hint,
        );
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    private function publicDevice(array $device): array
    {
        return [
            'name' => $this->stringOrNull($device['name'] ?? null) ?? 'Unknown Device',
            'class' => $this->stringOrNull($device['class'] ?? $device['usb_class'] ?? null) ?? '',
            'status' => $this->stringOrNull($device['status'] ?? null) ?? '',
            'vid' => $this->normalizeId($device['vid'] ?? null),
            'pid' => $this->normalizeId($device['pid'] ?? null),
            'hardware_ids' => is_array($device['hardware_ids'] ?? null)
                ? array_values(array_filter(array_map(fn (mixed $id): ?string => $this->stringOrNull($id), $device['hardware_ids'])))
                : [],
            'instance_id' => $this->stringOrNull($device['instance_id'] ?? $device['device_instance_id'] ?? null) ?? '',
            'location' => $this->stringOrNull($device['location'] ?? null) ?? '',
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $devices
     * @return array<int, array<string, mixed>>
     */
    private function buildDongleEntries(array $devices): array
    {
        $registered = $this->registeredDongles();
        $vendorNames = $this->vendorNames();
        $documentedPairs = $this->documentedPairs();
        $profiles = $this->mouseProfiles();
        $inputPairs = $this->inputPairs($devices);

        $groups = [];

        foreach ($devices as $device) {
            $vid = $this->normalizeId($device['vid'] ?? null);
            $pid = $this->normalizeId($device['pid'] ?? null);

            if ($vid === null || $pid === null || $vid === '8087') {
                continue;
            }

            $name = $this->stringOrNull($device['name'] ?? null) ?? 'Unknown Device';

            if (mb_stripos($name, 'bluetooth') !== false) {
                continue;
            }

            $pair = $vid.':'.$pid;
            $isReceiverName = preg_match(self::RECEIVER_NAME_PATTERN, $name) === 1;
            $isRegistered = isset($registered[$pair]);
            $isInputLike = $this->isInputLike($device);
            $hasInputChildren = isset($inputPairs[$pair]);

            if ($isInputLike && $this->hasNonInputSibling($devices, $device, $vid, $pid)) {
                continue;
            }

            if (! $isReceiverName && ! $isRegistered && ! ($hasInputChildren && ! $isInputLike)) {
                continue;
            }

            $location = $this->stringOrNull($device['location'] ?? null);
            $groupKey = $pair.'|'.($location ?? '');

            if (isset($groups[$groupKey])) {
                continue;
            }

            $groups[$groupKey] = [
                'device' => $device,
                'vid' => $vid,
                'pid' => $pid,
                'registered' => $registered[$pair] ?? null,
                'is_receiver_name' => $isReceiverName,
                'has_input_children' => $hasInputChildren,
            ];
        }

        $entries = [];
        $index = 0;

        foreach ($groups as $group) {
            $vid = $group['vid'];
            $pid = $group['pid'];
            /** @var Dongle|null $registeredDongle */
            $registeredDongle = $group['registered'];
            $documented = isset($documentedPairs[$vid.':'.$pid]) || $registeredDongle !== null;

            $entries[] = [
                'key' => $this->labelForIndex($index),
                'vid' => $vid,
                'pid' => $pid,
                'name' => $this->stringOrNull($group['device']['name'] ?? null) ?? 'Unknown Device',
                'brand_guess' => $this->brandGuess($vid, $pid, $registeredDongle, $vendorNames, $profiles, $group['device']),
                'receiver_family' => $this->receiverFamily($vid, $pid, $profiles),
                'location' => $this->stringOrNull($group['device']['location'] ?? null),
                'port' => $this->portFromLocation($this->stringOrNull($group['device']['location'] ?? null)),
                'dongle_id' => $registeredDongle?->id,
                'confidence' => $this->confidenceFor($vid, $pid, $documented, $vendorNames),
                'note' => $this->classificationNote($group, $documented),
                'matched_mice' => $this->matchedMiceForDongle($vid, $pid, $registeredDongle),
            ];
            $index++;
        }

        return $entries;
    }

    /**
     * @param  array<int, array<string, mixed>>  $dongles
     * @param  array<int, array<string, mixed>>  $connectedDevices
     * @return array<int, array<string, mixed>>
     */
    private function attributeDevices(array $dongles, array $connectedDevices, ?string $testedDongleKey): array
    {
        $attributions = [];
        $dongleCount = count(array_filter($dongles, 'is_array'));
        $testedDongle = null;

        if ($testedDongleKey !== null) {
            foreach ($dongles as $dongle) {
                if (is_array($dongle) && $this->stringOrNull($dongle['key'] ?? null) === $testedDongleKey) {
                    $testedDongle = $dongle;

                    break;
                }
            }
        }

        foreach ($connectedDevices as $device) {
            $matches = [];

            foreach ($dongles as $dongle) {
                if (! is_array($dongle)) {
                    continue;
                }

                $dongleVid = $this->normalizeId($dongle['vid'] ?? null);
                $donglePid = $this->normalizeId($dongle['pid'] ?? null);

                if ($dongleVid === null || $donglePid === null) {
                    continue;
                }

                $topology = $this->topologyMatch($device, $dongleVid, $donglePid);

                if ($topology === null) {
                    continue;
                }

                $key = $this->stringOrNull($dongle['key'] ?? null) ?? '?';
                $direct = $topology['direct'];

                $matches[] = [
                    'dongle_key' => $key,
                    'dongle_id' => is_int($dongle['dongle_id'] ?? null) ? $dongle['dongle_id'] : null,
                    'vid' => $dongleVid,
                    'pid' => $donglePid,
                    'reason' => $direct
                        ? sprintf(
                            'The mouse shares VID:PID %s:%s with dongle %s (Windows USB composite topology). Confirm with a physical pairing test before saving a match.',
                            $dongleVid,
                            $donglePid,
                            $key,
                        )
                        : sprintf(
                            'A hardware ID of the mouse contains dongle %s VID/PID %s:%s. Confirm with a physical pairing test before saving a match.',
                            $key,
                            $dongleVid,
                            $donglePid,
                        ),
                    'evidence' => $topology['evidence'],
                    'confidence' => $direct ? 'high' : 'medium',
                ];
            }

            if ($testedDongle !== null) {
                $this->addUserTestCandidate($matches, $testedDongle, $testedDongleKey, $dongleCount, $device);
            }

            $attributions[] = [
                'device' => $device,
                'device_instance_id' => $this->stringOrNull($device['instance_id'] ?? null),
                'candidates' => $matches,
                'message' => $matches === []
                    ? 'This device could not be attributed to any detected dongle by VID/PID or hardware IDs.'
                    : (count($matches) > 1
                        ? 'Multiple dongles match these identifiers. Choose the correct one by USB port/location and confirm with a physical pairing test.'
                        : null),
            ];
        }

        return $attributions;
    }

    /**
     * Records the dongle the operator declares they are testing. This is a
     * user observation, never an inferred identifier match. When the same
     * dongle already has a topology candidate, the declaration is merged into
     * it so the UI shows one honest row per labelled dongle.
     *
     * @param  array<int, array<string, mixed>>  $matches
     * @param  array<string, mixed>  $testedDongle
     * @param  array<string, mixed>  $device
     */
    private function addUserTestCandidate(array &$matches, array $testedDongle, string $testedDongleKey, int $dongleCount, array $device): void
    {
        $testedVid = $this->normalizeId($testedDongle['vid'] ?? null);
        $testedPid = $this->normalizeId($testedDongle['pid'] ?? null);
        $topology = $testedVid !== null && $testedPid !== null
            ? $this->topologyMatch($device, $testedVid, $testedPid)
            : null;
        $isHigh = $dongleCount === 1 || ($topology !== null && $topology['direct']);

        $identifier = $testedVid !== null && $testedPid !== null
            ? $testedVid.':'.$testedPid
            : 'unknown VID/PID';
        $evidence = $this->evidence(
            'user_test',
            sprintf(
                'The user selected labelled dongle %s (%s) as the device under test while mouse device "%s" was present.',
                $testedDongleKey,
                $identifier,
                $this->stringOrNull($device['name'] ?? null) ?? 'Unknown Device',
            ),
            'user_declared',
        );
        $reason = sprintf(
            'You selected labelled dongle %s as the device under test while this mouse device was present. This is a user-declared observation, not an identifier match; confirm with a physical pairing test before saving a match.',
            $testedDongleKey,
        );

        foreach ($matches as $index => $match) {
            if (($match['dongle_key'] ?? null) !== $testedDongleKey) {
                continue;
            }

            $matches[$index]['evidence'] = array_merge([$evidence], $match['evidence'] ?? []);
            $matches[$index]['reason'] = $reason;

            if ($isHigh) {
                $matches[$index]['confidence'] = 'high';
            }

            return;
        }

        $matches[] = [
            'dongle_key' => $testedDongleKey,
            'dongle_id' => is_int($testedDongle['dongle_id'] ?? null) ? $testedDongle['dongle_id'] : null,
            'vid' => $testedVid,
            'pid' => $testedPid,
            'reason' => $reason,
            'evidence' => [$evidence],
            'confidence' => $isHigh ? 'high' : 'medium',
        ];
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array{direct: bool, evidence: array<int, array<string, string>>}|null
     */
    private function topologyMatch(array $device, string $vid, string $pid): ?array
    {
        $evidence = [];
        $direct = false;

        $deviceVid = $this->normalizeId($device['vid'] ?? null);
        $devicePid = $this->normalizeId($device['pid'] ?? null);

        if ($deviceVid === $vid && $devicePid === $pid) {
            $direct = true;
            $evidence[] = $this->evidence(
                'topology',
                sprintf('Device shares VID:PID %s:%s reported for the dongle.', $vid, $pid),
                'windows_pnp',
            );
        }

        $hardwareIds = is_array($device['hardware_ids'] ?? null) ? $device['hardware_ids'] : [];

        foreach ($hardwareIds as $hardwareId) {
            $hardwareId = $this->stringOrNull($hardwareId);

            if ($hardwareId === null) {
                continue;
            }

            $parsed = $this->parseIds($hardwareId);

            if ($parsed['vid'] === $vid && $parsed['pid'] === $pid) {
                $evidence[] = $this->evidence(
                    'hardware_id',
                    sprintf("Hardware ID '%s' contains the dongle's VID/PID %s:%s.", $hardwareId, $vid, $pid),
                    'windows_pnp_hardware_ids',
                );
            }
        }

        if ($evidence === []) {
            return null;
        }

        return ['direct' => $direct, 'evidence' => $evidence];
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    private function snapshotDevice(array $device, string $scannedAt): array
    {
        return [
            'name' => $this->stringOrNull($device['name'] ?? null) ?? 'Unknown Device',
            'class' => $this->stringOrNull($device['usb_class'] ?? $device['class'] ?? null) ?? '',
            'status' => $this->stringOrNull($device['status'] ?? null) ?? '',
            'vid' => $this->normalizeId($device['vid'] ?? null),
            'pid' => $this->normalizeId($device['pid'] ?? null),
            'hardware_ids' => is_array($device['hardware_ids'] ?? null)
                ? array_values(array_filter(array_map(fn (mixed $id): ?string => $this->stringOrNull($id), $device['hardware_ids'])))
                : [],
            'instance_id' => $this->stringOrNull($device['device_instance_id'] ?? $device['instance_id'] ?? null) ?? '',
            'location' => $this->stringOrNull($device['location'] ?? null) ?? '',
            'detected_at' => $this->stringOrNull($device['detected_at'] ?? null) ?? $scannedAt,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $previousDevices
     * @return array<int, string>
     */
    private function previousInstanceIds(array $previousDevices): array
    {
        $ids = [];

        foreach ($previousDevices as $device) {
            if (! is_array($device)) {
                continue;
            }

            $id = $this->stringOrNull($device['instance_id'] ?? $device['device_instance_id'] ?? null);

            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    /**
     * @param  array<int, array<string, mixed>>  $devices
     * @return array<string, true>
     */
    private function inputPairs(array $devices): array
    {
        $pairs = [];

        foreach ($devices as $device) {
            $vid = $this->normalizeId($device['vid'] ?? null);
            $pid = $this->normalizeId($device['pid'] ?? null);

            if ($vid !== null && $pid !== null && $this->isInputLike($device)) {
                $pairs[$vid.':'.$pid] = true;
            }
        }

        return $pairs;
    }

    /**
     * @param  array<int, array<string, mixed>>  $devices
     * @param  array<string, mixed>  $device
     */
    private function hasNonInputSibling(array $devices, array $device, string $vid, string $pid): bool
    {
        $instanceId = $this->stringOrNull($device['device_instance_id'] ?? $device['instance_id'] ?? null);
        $location = $this->stringOrNull($device['location'] ?? null);

        foreach ($devices as $other) {
            $otherInstanceId = $this->stringOrNull($other['device_instance_id'] ?? $other['instance_id'] ?? null);

            if ($instanceId !== null && $otherInstanceId === $instanceId) {
                continue;
            }

            if ($this->normalizeId($other['vid'] ?? null) !== $vid
                || $this->normalizeId($other['pid'] ?? null) !== $pid
                || $this->stringOrNull($other['location'] ?? null) !== $location) {
                continue;
            }

            if (! $this->isInputLike($other)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function isInputLike(array $device): bool
    {
        $class = mb_strtolower($this->stringOrNull($device['usb_class'] ?? $device['class'] ?? null) ?? '');
        $name = $this->stringOrNull($device['name'] ?? null) ?? '';

        if (in_array($class, ['mouse', 'hidclass'], true)) {
            return true;
        }

        return preg_match('/mouse|pointing|keyboard|hid[- ]?(compliant|device)/i', $name) === 1;
    }

    /**
     * A device must carry a VID/PID directly or through its hardware/instance IDs.
     *
     * @param  array<string, mixed>  $device
     */
    private function deviceHasUsbIds(array $device): bool
    {
        if ($this->normalizeId($device['vid'] ?? null) !== null
            && $this->normalizeId($device['pid'] ?? null) !== null) {
            return true;
        }

        $identifiers = is_array($device['hardware_ids'] ?? null) ? $device['hardware_ids'] : [];
        $identifiers[] = $this->stringOrNull($device['instance_id'] ?? null) ?? '';

        foreach ($identifiers as $identifier) {
            $identifier = $this->stringOrNull($identifier);

            if ($identifier === null) {
                continue;
            }

            $parsed = $this->parseIds($identifier);

            if ($parsed['vid'] !== null && $parsed['pid'] !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function isMouseLike(array $device): bool
    {
        $name = $this->stringOrNull($device['name'] ?? null) ?? 'Unknown Device';
        $class = mb_strtolower($this->stringOrNull($device['class'] ?? $device['usb_class'] ?? null) ?? '');

        if (! $this->deviceHasUsbIds($device)) {
            return false;
        }

        if (preg_match('/keyboard|system controller|consumer control|sensor|bluetooth|minidriver|\biss\b|\bpci\b|portable device|control device/i', $name) === 1) {
            return false;
        }

        if (preg_match(self::RECEIVER_NAME_PATTERN, $name) === 1) {
            return false;
        }

        if ($class === 'mouse' || preg_match('/mouse|pointing|trackpad|touchpad/i', $name) === 1) {
            return true;
        }

        if ($class !== 'hidclass') {
            return false;
        }

        return preg_match('/compliant|vendor-defined|input device|unknown device/i', $name) !== 1;
    }

    /**
     * @return array<string, Dongle>
     */
    private function registeredDongles(): array
    {
        $registered = [];

        foreach (Dongle::query()->whereNotNull('vid')->whereNotNull('pid')->orderBy('id')->get() as $dongle) {
            $vid = $this->normalizeId($dongle->vid);
            $pid = $this->normalizeId($dongle->pid);

            if ($vid !== null && $pid !== null) {
                $registered[$vid.':'.$pid] = $dongle;
            }
        }

        return $registered;
    }

    /**
     * @return array<string, string>
     */
    private function vendorNames(): array
    {
        if (! Schema::hasTable('usb_vendors')) {
            return [];
        }

        $names = [];

        foreach (DB::table('usb_vendors')->get() as $row) {
            $vid = $this->normalizeId($row->vid ?? null);

            if ($vid !== null) {
                $names[$vid] = $this->stringOrNull($row->vendor_name) ?? '';
            }
        }

        return $names;
    }

    /**
     * @return array<string, true>
     */
    private function documentedPairs(): array
    {
        $pairs = [];

        if (Schema::hasTable('usb_products')) {
            foreach (DB::table('usb_products')->get() as $row) {
                $vid = $this->normalizeId($row->vid ?? null);
                $pid = $this->normalizeId($row->pid ?? null);

                if ($vid !== null && $pid !== null) {
                    $pairs[$vid.':'.$pid] = true;
                }
            }
        }

        if (Schema::hasTable('mouse_profiles')) {
            foreach (DB::table('mouse_profiles')->get() as $row) {
                $vid = $this->normalizeId($row->vid ?? null);
                $pid = $this->normalizeId($row->pid ?? null);

                if ($vid !== null && $pid !== null) {
                    $pairs[$vid.':'.$pid] = true;
                }
            }
        }

        return $pairs;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function mouseProfiles(): array
    {
        if (! Schema::hasTable('mouse_profiles')) {
            return [];
        }

        return array_map(
            fn (object $row): array => (array) $row,
            DB::table('mouse_profiles')->get()->all(),
        );
    }

    /**
     * @param  array<string, string>  $vendorNames
     * @param  array<int, array<string, mixed>>  $profiles
     * @param  array<string, mixed>  $device
     */
    private function brandGuess(
        string $vid,
        string $pid,
        ?Dongle $registeredDongle,
        array $vendorNames,
        array $profiles,
        array $device,
    ): string {
        if (isset(self::VENDOR_BRANDS[$vid])) {
            return self::VENDOR_BRANDS[$vid];
        }

        $vendorName = $this->stringOrNull($vendorNames[$vid] ?? null);

        if ($vendorName !== null && $vendorName !== '') {
            return $vendorName;
        }

        foreach ($profiles as $profile) {
            if ($this->normalizeId($profile['vid'] ?? null) === $vid
                && $this->normalizeId($profile['pid'] ?? null) === $pid) {
                $brand = $this->stringOrNull($profile['brand'] ?? null);

                if ($brand !== null) {
                    return $brand;
                }
            }
        }

        $usbIdsVendor = $this->usbIdsVendorName($vid);

        if ($usbIdsVendor !== null) {
            return $usbIdsVendor;
        }

        $registeredBrand = $registeredDongle !== null ? $this->stringOrNull($registeredDongle->brand) : null;
        $manufacturer = $this->stringOrNull($device['manufacturer'] ?? null);

        return $registeredBrand ?? $manufacturer ?? 'Unknown';
    }

    private function usbIdsVendorName(string $vid): ?string
    {
        return $this->stringOrNull($this->parsedUsbIds()['vendors'][$vid] ?? null);
    }

    private function usbIdsProductName(string $vid, string $pid): ?string
    {
        return $this->stringOrNull($this->parsedUsbIds()['products'][$vid.':'.$pid] ?? null);
    }

    /**
     * Parses the optional local usb.ids file once per request. Vendor lines
     * start at column 0; device lines start with a tab and belong to the most
     * recent vendor. Anything else (comments, class sections) cannot add a
     * product, and an absent or unreadable file yields an empty map.
     *
     * @return array{vendors: array<string, string>, products: array<string, string>}
     */
    private function parsedUsbIds(): array
    {
        if ($this->usbIdsMap !== null) {
            return $this->usbIdsMap;
        }

        $map = ['vendors' => [], 'products' => []];
        $path = $this->usbIdsPath ?? storage_path('app/usbids/usb.ids');

        if (is_file($path) && is_readable($path)) {
            $lines = @file($path, FILE_IGNORE_NEW_LINES);

            if (is_array($lines)) {
                $currentVendor = null;

                foreach ($lines as $line) {
                    if (preg_match('/^\t+([0-9A-Fa-f]{4})\s+(.+)$/', $line, $matches) === 1) {
                        if ($currentVendor !== null) {
                            $productName = trim($matches[2]);

                            if ($productName !== '') {
                                $map['products'][$currentVendor.':'.strtoupper($matches[1])] = $productName;
                            }
                        }

                        continue;
                    }

                    if (preg_match('/^([0-9A-Fa-f]{4})\s+(.+)$/', $line, $matches) === 1) {
                        $currentVendor = strtoupper($matches[1]);
                        $vendorName = trim($matches[2]);

                        if ($vendorName !== '') {
                            $map['vendors'][$currentVendor] = $vendorName;
                        }

                        continue;
                    }

                    if ($line !== '' && ! str_starts_with($line, '#') && ! str_starts_with($line, "\t")) {
                        $currentVendor = null;
                    }
                }
            }
        }

        return $this->usbIdsMap = $map;
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     */
    private function receiverFamily(string $vid, string $pid, array $profiles): ?string
    {
        $pair = $vid.':'.$pid;

        if (isset(self::RECEIVER_FAMILIES[$pair])) {
            return self::RECEIVER_FAMILIES[$pair];
        }

        foreach ($profiles as $profile) {
            if ($this->normalizeId($profile['vid'] ?? null) === $vid
                && $this->normalizeId($profile['pid'] ?? null) === $pid) {
                $family = $this->stringOrNull($profile['receiver_family'] ?? null);

                if ($family !== null) {
                    return $family;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $vendorNames
     */
    private function confidenceFor(string $vid, string $pid, bool $documented, array $vendorNames): string
    {
        if ($documented || $this->usbIdsProductName($vid, $pid) !== null) {
            return 'high';
        }

        if (isset(self::VENDOR_BRANDS[$vid])
            || $this->stringOrNull($vendorNames[$vid] ?? null) !== null
            || $this->usbIdsVendorName($vid) !== null) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  array<string, mixed>  $group
     */
    private function classificationNote(array $group, bool $documented): string
    {
        $notes = [];

        if ($group['registered'] instanceof Dongle) {
            $notes[] = 'Registered dongle '.$group['registered']->dongle_id;
        }

        if ($group['is_receiver_name']) {
            $notes[] = 'name matches a receiver pattern';
        }

        if ($group['has_input_children'] && ! $this->isInputLike($group['device'])) {
            $notes[] = 'USB composite device with HID/mouse children sharing VID:PID';
        }

        $notes[] = $documented
            ? 'VID:PID documented in local reference data'
            : 'VID:PID not documented locally; identifiers are a family clue only';

        return implode('; ', $notes).'.';
    }

    private function portFromLocation(?string $location): ?string
    {
        if ($location === null || $location === '') {
            return null;
        }

        if (preg_match('/Port[_#\s]*#?(\d+)/i', $location, $matches) === 1) {
            return 'Port_#'.str_pad($matches[1], 4, '0', STR_PAD_LEFT);
        }

        return $location;
    }

    /**
     * Recorded mouse relationships for the plugged receiver.
     *
     * When the receiver itself is not registered, matches from registered
     * dongles with the same VID:PID are included but marked as family-level
     * clues — identical receivers cannot be distinguished electronically.
     *
     * @return array<int, array<string, mixed>>
     */
    private function matchedMiceForDongle(string $vid, string $pid, ?Dongle $registeredDongle): array
    {
        $dongleIds = Dongle::query()
            ->where('vid', $vid)
            ->where('pid', $pid)
            ->pluck('id')
            ->all();

        if ($registeredDongle !== null && ! in_array($registeredDongle->id, $dongleIds, true)) {
            $dongleIds[] = $registeredDongle->id;
        }

        if ($dongleIds === []) {
            return [];
        }

        return DeviceMatch::query()
            ->with('mouse')
            ->whereIn('dongle_id', $dongleIds)
            ->latest()
            ->limit(50)
            ->get()
            ->map(function (DeviceMatch $match) use ($registeredDongle): array {
                $mouse = $match->mouse;

                return [
                    'match_id' => $match->id,
                    'status' => $match->status,
                    'notes' => $this->stringOrNull($match->notes),
                    'matched_at' => $match->matched_at?->toIso8601String(),
                    'dongle_id' => $match->dongle_id,
                    'match_source' => $registeredDongle !== null && $match->dongle_id === $registeredDongle->id
                        ? 'registered'
                        : 'same_vid_pid_family',
                    'mouse_id' => $mouse?->id,
                    'mouse_code' => $mouse?->mouse_id,
                    'brand' => $this->stringOrNull($mouse?->brand),
                    'model' => $this->stringOrNull($mouse?->model),
                    'photo_url' => $mouse?->photo_url,
                ];
            })
            ->all();
    }

    private function labelForIndex(int $index): string
    {
        $label = '';
        $number = $index + 1;

        while ($number > 0) {
            $number--;
            $label = chr(65 + ($number % 26)).$label;
            $number = intdiv($number, 26);
        }

        return $label;
    }

    /**
     * @return array{vid: string|null, pid: string|null}
     */
    private function parseIds(string $value): array
    {
        $vid = null;
        $pid = null;

        if (preg_match('/VID[_-]?([0-9A-Fa-f]{4})/', $value, $matches) === 1) {
            $vid = strtoupper($matches[1]);
        }

        if (preg_match('/PID[_-]?([0-9A-Fa-f]{4})/', $value, $matches) === 1) {
            $pid = strtoupper($matches[1]);
        }

        return ['vid' => $vid, 'pid' => $pid];
    }

    /**
     * @return array<string, string>
     */
    private function evidence(string $type, string $detail, string $source): array
    {
        return ['type' => $type, 'detail' => $detail, 'source' => $source];
    }

    /**
     * @return array{observed_at: string, devices: array<int, array<string, mixed>>, connected_devices: array<int, array<string, mixed>>, new_devices: array<int, array<string, mixed>>, candidates: array<int, array<string, mixed>>, flat_candidates: array<int, array<string, mixed>>, error: string, message: string, tested_dongle_key: string|null}
     */
    private function errorResult(string $observedAt, string $error, ?string $testedDongleKey): array
    {
        return [
            'observed_at' => $observedAt,
            'devices' => [],
            'connected_devices' => [],
            'new_devices' => [],
            'candidates' => [],
            'flat_candidates' => [],
            'error' => $error,
            'message' => 'Dongle Lab observation is unavailable: '.$error,
            'tested_dongle_key' => $testedDongleKey,
        ];
    }

    private function normalizeId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[0-9A-F]{4}$/', $value) === 1 ? $value : null;
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : null;
    }
}
