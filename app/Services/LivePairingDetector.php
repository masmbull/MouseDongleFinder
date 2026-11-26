<?php

namespace App\Services;

use App\Models\Dongle;
use App\Models\Mouse;
use App\Services\Concerns\RunsWindowsProcess;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Live, evidence-based pairing suggestions for dongles that are plugged in now.
 *
 * Only real Windows PnP topology (shared VID/PID or hardware IDs), registered
 * mouse data and optional mouse_profiles rows are used. Nothing is auto-saved:
 * the UI must present each suggestion and the user confirms the physical test.
 */
class LivePairingDetector
{
    use RunsWindowsProcess;

    private const MAX_DEVICES = 100;

    private const TIMEOUT_SECONDS = 40;

    /** Vendor IDs with documented wireless receiver families. */
    private const RECEIVER_VIDS = ['046D', '1532', '045E', '1B1C', '1038'];

    private const RECEIVER_NAME_PATTERN = '/receiver|dongle|unifying|nano|lightspeed|bolt|slipstream|hyperspeed/i';

    /** The receiver itself is not a mouse; deliberately excludes generic "wireless". */
    private const RECEIVER_DEVICE_PATTERN = '/receiver|dongle|unifying|nano|lightspeed|bolt|slipstream/i';

    /** Generic words ignored when comparing a device name with a registered model. */
    private const NAME_NOISE = [
        'usb', 'wireless', 'mouse', 'receiver', 'dongle', 'hid', 'compliant', 'device',
        'optical', 'gaming', 'bluetooth', 'the', 'and', 'for', 'with',
        'logitech', 'razer', 'microsoft', 'steelseries', 'corsair',
    ];

    /** @var array<string, string> */
    private const VENDOR_BRANDS = [
        '046D' => 'Logitech',
        '1532' => 'Razer',
        '045E' => 'Microsoft',
        '1B1C' => 'Corsair',
        '1038' => 'SteelSeries',
    ];

    public function detect(): array
    {
        $scannedAt = now()->toIso8601String();

        if (! $this->isWindowsHost()) {
            return $this->emptyResult($scannedAt, 'Live mouse detection is only available on Windows. The PnP cmdlets used by this feature do not exist on this host.');
        }

        $scriptPath = resource_path('ps/scan-connected-mice.ps1');

        if (! is_file($scriptPath)) {
            Log::error('Live detection script is missing.', ['path' => $scriptPath]);

            return $this->emptyResult($scannedAt, 'Live detection script is missing. Please reinstall the application files.');
        }

        $scan = $this->scanDevices($scriptPath);

        if ($scan['error'] !== null) {
            return $this->emptyResult($scannedAt, $scan['error']);
        }

        return $this->buildResult($scan['devices'], $scannedAt);
    }

    /**
     * Reads the raw PnP device list without building pairing suggestions.
     *
     * Dongle Lab uses this to diff the devices present before and after a mouse
     * is switched on. The returned devices use the same public shape as
     * {@see self::publicDevice()} so snapshots from different sources compare
     * cleanly by instance ID. Nothing is inferred or saved here.
     *
     * @return array{devices: array<int, array<string, mixed>>, error: string|null, scanned_at: string}
     */
    public function scanOnly(): array
    {
        $scannedAt = now()->toIso8601String();

        if (! $this->isWindowsHost()) {
            return [
                'devices' => [],
                'error' => 'Live mouse detection is only available on Windows. The PnP cmdlets used by this feature do not exist on this host.',
                'scanned_at' => $scannedAt,
            ];
        }

        $scriptPath = resource_path('ps/scan-connected-mice.ps1');

        if (! is_file($scriptPath)) {
            Log::error('Live detection script is missing.', ['path' => $scriptPath]);

            return [
                'devices' => [],
                'error' => 'Live detection script is missing. Please reinstall the application files.',
                'scanned_at' => $scannedAt,
            ];
        }

        $scan = $this->scanDevices($scriptPath);

        if ($scan['error'] !== null) {
            return [
                'devices' => [],
                'error' => $scan['error'],
                'scanned_at' => $scannedAt,
            ];
        }

        $devices = [];

        foreach ($scan['devices'] as $rawDevice) {
            if (! is_array($rawDevice)) {
                continue;
            }

            $devices[] = $this->publicDevice($this->normalizeDevice($rawDevice, $scannedAt));

            if (count($devices) >= self::MAX_DEVICES) {
                break;
            }
        }

        return [
            'devices' => $devices,
            'error' => null,
            'scanned_at' => $scannedAt,
        ];
    }

    protected function isWindowsHost(): bool
    {
        return PHP_OS_FAMILY === 'Windows';
    }

    /**
     * @return array{devices: array<int, array<string, mixed>>, error: string|null}
     */
    protected function scanDevices(string $scriptPath): array
    {
        $process = new Process([
            'powershell.exe',
            '-NoProfile',
            '-NonInteractive',
            '-ExecutionPolicy',
            'Bypass',
            '-File',
            $scriptPath,
        ]);
        $process->setTimeout(self::TIMEOUT_SECONDS);

        try {
            $this->runWithCleanEnvironment($process);
        } catch (Throwable $exception) {
            Log::error('Live detection process could not be started.', ['message' => $exception->getMessage()]);

            return ['devices' => [], 'error' => 'Live detection could not be started: '.$exception->getMessage()];
        }

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput()) ?: 'Unknown PowerShell error.';
            Log::error('Live detection process failed.', ['error' => $errorOutput]);

            return ['devices' => [], 'error' => 'Live detection failed: '.$errorOutput];
        }

        $decoded = json_decode($process->getOutput(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Live detection returned invalid JSON.', ['error' => json_last_error_msg()]);

            return ['devices' => [], 'error' => 'Live detection returned data that could not be parsed.'];
        }

        $devices = is_array($decoded) && array_is_list($decoded)
            ? $decoded
            : (is_array($decoded) ? [$decoded] : []);

        return ['devices' => $devices, 'error' => null];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rawDevices
     */
    private function buildResult(array $rawDevices, string $scannedAt): array
    {
        $devices = [];

        foreach ($rawDevices as $rawDevice) {
            if (! is_array($rawDevice)) {
                continue;
            }

            $device = $this->normalizeDevice($rawDevice, $scannedAt);

            if ($device !== null) {
                $devices[] = $device;
            }

            if (count($devices) >= self::MAX_DEVICES) {
                break;
            }
        }

        $profiles = $this->mouseProfiles();
        $registeredMice = Mouse::query()->orderBy('id')->get()->all();
        $dongleCandidates = $this->dongleCandidates($devices);

        $dongles = [];
        $attributedIndices = [];

        foreach ($dongleCandidates as $candidate) {
            $connected = [];

            foreach ($devices as $deviceIndex => $device) {
                if (isset($attributedIndices[$deviceIndex]) || ! $this->deviceBelongsToDongle($device, $candidate)) {
                    continue;
                }

                $attributedIndices[$deviceIndex] = true;
                $connected[] = ['index' => count($connected), 'device' => $device];
            }

            $suggestions = [];

            foreach ($connected as $connectedDevice) {
                if ($this->isReceiverItself($connectedDevice['device']) || ! $this->isMouseLike($connectedDevice['device'])) {
                    continue;
                }

                $topologyEvidence = $this->topologyEvidence($connectedDevice['device'], $candidate);

                foreach ($this->suggestionsForDevice(
                    $connectedDevice['device'],
                    $connectedDevice['index'],
                    $topologyEvidence,
                    $profiles,
                    $registeredMice,
                ) as $suggestion) {
                    $suggestions[] = $suggestion;
                }
            }

            $dongles[] = [
                'vid' => $candidate['vid'],
                'pid' => $candidate['pid'],
                'name' => $candidate['name'],
                'dongle_id' => $candidate['dongle_id'],
                'connected_devices' => array_map(
                    fn (array $item): array => $this->publicDevice($item['device']),
                    $connected,
                ),
                'suggestions' => $suggestions,
            ];
        }

        $unattributedDevices = [];

        foreach ($devices as $deviceIndex => $device) {
            if (! isset($attributedIndices[$deviceIndex])) {
                $unattributedDevices[] = $this->publicDevice($device);
            }
        }

        return [
            'scanned_at' => $scannedAt,
            'error' => null,
            'dongles' => $dongles,
            'unattributed_devices' => $unattributedDevices,
            'message' => $this->buildMessage($dongles, $unattributedDevices),
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeDevice(array $raw, string $scannedAt): array
    {
        $instanceId = $this->stringOrNull($raw['instance_id'] ?? null)
            ?? $this->stringOrNull($raw['device_instance_id'] ?? null)
            ?? '';

        $vid = $this->normalizeId($raw['vid'] ?? null);
        $pid = $this->normalizeId($raw['pid'] ?? null);

        if ($vid === null || $pid === null) {
            $parsed = $this->parseIds($instanceId);
            $vid ??= $parsed['vid'];
            $pid ??= $parsed['pid'];
        }

        $hardwareIds = $raw['hardware_ids'] ?? [];
        $hardwareIds = is_array($hardwareIds)
            ? array_values(array_filter(array_map(fn (mixed $id): ?string => $this->stringOrNull($id), $hardwareIds)))
            : [];

        return [
            'name' => $this->stringOrNull($raw['name'] ?? null) ?? 'Unknown Device',
            'class' => $this->stringOrNull($raw['class'] ?? null) ?? '',
            'status' => $this->stringOrNull($raw['status'] ?? null) ?? '',
            'vid' => $vid,
            'pid' => $pid,
            'hardware_ids' => $hardwareIds,
            'instance_id' => $instanceId,
            'location' => $this->stringOrNull($raw['location'] ?? null) ?? '',
            'manufacturer' => $this->stringOrNull($raw['manufacturer'] ?? null),
            'description' => $this->stringOrNull($raw['description'] ?? null),
            'is_input' => (bool) ($raw['is_input'] ?? false),
            'detected_at' => $this->stringOrNull($raw['detected_at'] ?? null) ?? $scannedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $device
     * @return array<string, mixed>
     */
    private function publicDevice(array $device): array
    {
        return [
            'name' => $device['name'],
            'class' => $device['class'],
            'status' => $device['status'],
            'vid' => $device['vid'],
            'pid' => $device['pid'],
            'hardware_ids' => $device['hardware_ids'],
            'instance_id' => $device['instance_id'],
            'location' => $device['location'],
        ];
    }

    /**
     * Only receivers that are actually present in the current scan become candidates.
     * A registered dongle is used to attach its database id when its VID:PID is seen;
     * otherwise a receiver is recognised by its name or by having input devices with
     * the same VID:PID (a composite USB receiver with HID children). No hardware is
     * invented and sample dongles that are not plugged in never appear here.
     *
     * @param  array<int, array<string, mixed>>  $devices
     * @return array<string, array{vid: string, pid: string, name: string, dongle_id: int|null}>
     */
    private function dongleCandidates(array $devices): array
    {
        $registered = [];

        foreach (Dongle::query()->whereNotNull('vid')->whereNotNull('pid')->orderBy('id')->get() as $dongle) {
            $vid = $this->normalizeId($dongle->vid);
            $pid = $this->normalizeId($dongle->pid);

            if ($vid !== null && $pid !== null) {
                $registered[$vid.':'.$pid] = $dongle;
            }
        }

        $inputIds = [];

        foreach ($devices as $device) {
            if ($device['is_input'] && $device['vid'] !== null && $device['pid'] !== null) {
                $inputIds[$device['vid'].':'.$device['pid']] = true;
            }
        }

        $candidates = [];

        foreach ($devices as $device) {
            $vid = $device['vid'];
            $pid = $device['pid'];

            if ($vid === null || $pid === null || $vid === '8087') {
                continue;
            }

            $key = $vid.':'.$pid;
            $dongle = $registered[$key] ?? null;
            $hasInputChildren = isset($inputIds[$key]);

            if ($dongle === null && ! $this->looksLikeReceiver($device) && ! $hasInputChildren) {
                continue;
            }

            if (isset($candidates[$key])) {
                if ($dongle !== null && $candidates[$key]['dongle_id'] === null) {
                    $candidates[$key]['dongle_id'] = $dongle->id;
                    $candidates[$key]['name'] = $this->dongleName($dongle, $vid, $pid);
                }

                continue;
            }

            $candidates[$key] = [
                'vid' => $vid,
                'pid' => $pid,
                'name' => $dongle !== null
                    ? $this->dongleName($dongle, $vid, $pid)
                    : ($this->looksLikeReceiver($device)
                        ? $device['name']
                        : $device['name'].' (USB composite input device)'),
                'dongle_id' => $dongle?->id,
            ];
        }

        return $candidates;
    }

    private function dongleName(Dongle $dongle, string $vid, string $pid): string
    {
        $parts = array_filter([
            $this->stringOrNull($dongle->brand),
            $this->stringOrNull($dongle->model),
            $this->stringOrNull($dongle->receiver_type),
        ]);

        if ($parts === []) {
            return 'USB Receiver '.$vid.':'.$pid;
        }

        $name = implode(' ', $parts);
        $receiverType = $this->stringOrNull($dongle->receiver_type);

        if ($receiverType !== null && ! str_ends_with(mb_strtolower($name), 'receiver')) {
            $name .= ' Receiver';
        }

        return $name;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function looksLikeReceiver(array $device): bool
    {
        // Internal Bluetooth adapters (e.g. Intel 8087:0026) are not wireless
        // mouse receivers and must not be presented as dongle candidates.
        if ($device['vid'] === '8087' || mb_stripos($device['name'], 'bluetooth') !== false) {
            return false;
        }

        if (preg_match(self::RECEIVER_NAME_PATTERN, $device['name']) === 1) {
            return true;
        }

        return $device['class'] === 'HIDClass' && in_array($device['vid'], self::RECEIVER_VIDS, true);
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function isMouseLike(array $device): bool
    {
        $name = $device['name'];

        if (preg_match('/keyboard|system controller|consumer control|sensor|bluetooth/i', $name) === 1) {
            return false;
        }

        if ($device['class'] === 'Mouse' || preg_match('/mouse|pointing|trackpad|touchpad/i', $name) === 1) {
            return true;
        }

        if ($device['class'] !== 'HIDClass') {
            return false;
        }

        // A generic HID interface is not proof of a mouse. Named devices such
        // as "MX Master 3S" are allowed; generic Windows labels are not.
        return preg_match('/compliant|vendor-defined|input device|unknown device/i', $name) !== 1;
    }

    /**
     * @param  array<string, mixed>  $device
     */
    private function isReceiverItself(array $device): bool
    {
        if ($device['class'] === 'Mouse') {
            return false;
        }

        return preg_match(self::RECEIVER_DEVICE_PATTERN, $device['name']) === 1;
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  array{vid: string, pid: string, name: string, dongle_id: int|null}  $dongle
     */
    private function deviceBelongsToDongle(array $device, array $dongle): bool
    {
        if ($device['vid'] !== null && $device['pid'] !== null
            && $device['vid'] === $dongle['vid'] && $device['pid'] === $dongle['pid']) {
            return true;
        }

        foreach ($device['hardware_ids'] as $hardwareId) {
            $parsed = $this->parseIds($hardwareId);

            if ($parsed['vid'] === $dongle['vid'] && $parsed['pid'] === $dongle['pid']) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  array{vid: string, pid: string, name: string, dongle_id: int|null}  $dongle
     * @return array<int, array<string, string>>
     */
    private function topologyEvidence(array $device, array $dongle): array
    {
        $evidence = [];

        if ($device['vid'] !== null && $device['pid'] !== null
            && $device['vid'] === $dongle['vid'] && $device['pid'] === $dongle['pid']) {
            $evidence[] = $this->evidence(
                'topology',
                "Device shares the dongle's VID:PID {$device['vid']}:{$device['pid']} (USB composite interface).",
                'windows_pnp',
            );
        }

        foreach ($device['hardware_ids'] as $hardwareId) {
            $parsed = $this->parseIds($hardwareId);

            if ($parsed['vid'] === $dongle['vid'] && $parsed['pid'] === $dongle['pid']) {
                $evidence[] = $this->evidence(
                    'topology',
                    "Hardware ID '{$hardwareId}' contains the dongle's VID/PID {$dongle['vid']}:{$dongle['pid']}.",
                    'windows_pnp_hardware_ids',
                );

                break;
            }
        }

        return $evidence;
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  array<int, array<string, mixed>>  $profiles
     * @param  array<int, Mouse>  $registeredMice
     * @return array<int, array<string, mixed>>
     */
    private function suggestionsForDevice(
        array $device,
        int $deviceIndex,
        array $topologyEvidence,
        array $profiles,
        array $registeredMice,
    ): array {
        $deviceTokens = $this->nameTokens($device['name']);
        $candidates = [];

        foreach ($registeredMice as $mouse) {
            $brand = $this->stringOrNull($mouse->brand) ?? '';
            $model = $this->stringOrNull($mouse->model) ?? '';
            $evidence = $topologyEvidence;

            $brandInName = $brand !== '' && str_contains(mb_strtolower($device['name']), mb_strtolower($brand));
            $vidBrand = $device['vid'] !== null ? (self::VENDOR_BRANDS[$device['vid']] ?? null) : null;
            $brandFromVid = $vidBrand !== null && $this->sameBrand($brand, $vidBrand);

            if ($brandInName || $brandFromVid) {
                $evidence[] = $this->evidence(
                    'brand',
                    $brandInName
                        ? "Device name '{$device['name']}' contains the registered brand '{$brand}'."
                        : "Device VID {$device['vid']} is registered to '{$vidBrand}', matching the mouse brand '{$brand}'.",
                    $brandInName ? 'windows_pnp' : 'usb_vendor_registry',
                );
            }

            $modelTokens = $this->nameTokens($model);
            $exactNameMatch = $modelTokens !== [] && $this->containsAllTokens($deviceTokens, $modelTokens);
            $partialTokens = $this->matchingTokens($deviceTokens, $modelTokens);

            if ($exactNameMatch) {
                $evidence[] = $this->evidence(
                    'device_name',
                    "Device name '{$device['name']}' contains the full registered model '{$model}'.",
                    'windows_pnp',
                );
            } elseif ($partialTokens !== []) {
                $evidence[] = $this->evidence(
                    'device_name',
                    "Device name '{$device['name']}' contains model token(s): ".implode(', ', $partialTokens).'.',
                    'windows_pnp',
                );
            }

            $profilePidMatch = false;
            $profileModelMatch = false;

            foreach ($profiles as $profile) {
                $profileBrand = $this->stringOrNull($profile['brand'] ?? null) ?? '';
                $profileModel = $this->stringOrNull($profile['model'] ?? null) ?? '';
                $profileVid = $this->normalizeId($profile['vid'] ?? null);
                $profilePid = $this->normalizeId($profile['pid'] ?? null);

                if (! $this->sameBrand($brand, $profileBrand)
                    || ($model !== '' && $profileModel !== '' && ! $this->sameModel($model, $profileModel))) {
                    continue;
                }

                if ($profileVid !== null && $profilePid !== null
                    && $profileVid === $device['vid'] && $profilePid === $device['pid']) {
                    $profilePidMatch = true;
                    $evidence[] = $this->evidence(
                        'mouse_profile',
                        "Device VID/PID {$device['vid']}:{$device['pid']} matches a mouse_profiles row for '{$profileBrand} {$profileModel}'.",
                        'mouse_profiles',
                    );
                }

                $profileTokens = $this->nameTokens($profileModel);

                if ($profileTokens !== [] && $this->containsAllTokens($deviceTokens, $profileTokens)) {
                    $profileModelMatch = true;
                    $evidence[] = $this->evidence(
                        'mouse_profile',
                        "Device name '{$device['name']}' matches the mouse_profiles model '{$profileModel}'.",
                        'mouse_profiles',
                    );
                }
            }

            if ($exactNameMatch || $profilePidMatch || $profileModelMatch) {
                $confidence = 'high';
            } elseif ($partialTokens !== []) {
                $confidence = 'medium';
            } elseif ($brandInName || $brandFromVid) {
                $confidence = 'low';
            } else {
                continue;
            }

            $candidates[] = [
                'mouse_id' => $mouse->id,
                'mouse' => ['brand' => $brand, 'model' => $model],
                'confidence' => $confidence,
                'evidence' => $evidence,
            ];
        }

        if ($candidates === []) {
            return [$this->pseudoSuggestion($device, $deviceIndex, $topologyEvidence, $profiles)];
        }

        $bestConfidence = 'low';

        foreach (['high', 'medium', 'low'] as $level) {
            if (array_filter($candidates, fn (array $candidate): bool => $candidate['confidence'] === $level) !== []) {
                $bestConfidence = $level;

                break;
            }
        }

        $suggestions = [];

        foreach ($candidates as $candidate) {
            if ($candidate['confidence'] !== $bestConfidence) {
                continue;
            }

            $suggestions[] = [
                'mouse_id' => $candidate['mouse_id'],
                'brand' => $candidate['mouse']['brand'],
                'model' => $candidate['mouse']['model'],
                'confidence' => $candidate['confidence'],
                'reason' => $this->buildReason($candidate['confidence'], $candidate['evidence'], $candidate['mouse']),
                'evidence' => $candidate['evidence'],
                'device_index' => $deviceIndex,
            ];

            if (count($suggestions) >= 5) {
                break;
            }
        }

        return $suggestions;
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  array<int, array<string, mixed>>  $profiles
     * @return array<string, mixed>
     */
    private function pseudoSuggestion(array $device, int $deviceIndex, array $topologyEvidence, array $profiles): array
    {
        $brand = self::VENDOR_BRANDS[$device['vid'] ?? ''] ?? '';
        $model = $device['name'];

        foreach ($profiles as $profile) {
            $profileVid = $this->normalizeId($profile['vid'] ?? null);
            $profilePid = $this->normalizeId($profile['pid'] ?? null);

            if ($profileVid !== null && $profilePid !== null
                && $profileVid === $device['vid'] && $profilePid === $device['pid']) {
                $brand = $this->stringOrNull($profile['brand'] ?? null) ?? $brand;
                $model = $this->stringOrNull($profile['model'] ?? null) ?? $model;

                break;
            }
        }

        $evidence = $topologyEvidence;
        $evidence[] = $this->evidence(
            'registration_required',
            'No registered mouse matched this connected device. Register the mouse before confirming a match.',
            'live_detection',
        );

        $hasUsableClue = $brand !== '' || ($model !== '' && $model !== 'Unknown Device');

        return [
            'mouse_id' => null,
            'brand' => $brand,
            'model' => $model,
            'confidence' => $hasUsableClue ? 'low' : 'unknown',
            'reason' => "No registered mouse matched. The detected name '{$device['name']}' suggests a mouse that is not registered yet; manual registration and a physical pairing test are required.",
            'evidence' => $evidence,
            'device_index' => $deviceIndex,
        ];
    }

    /**
     * @param  array<string, mixed>  $mouse
     */
    private function buildReason(string $confidence, array $evidence, array $mouse): string
    {
        $details = [];

        foreach ($evidence as $item) {
            if (in_array($item['type'], ['topology', 'device_name', 'mouse_profile'], true)) {
                $details[] = rtrim($item['detail'], '.');
            }
        }

        $summary = $details === [] ? 'Limited evidence.' : implode(' ', $details).'.';
        $label = trim($mouse['brand'].' '.$mouse['model']);

        return match ($confidence) {
            'high' => $summary." Strong match to {$label}. Confirm with a physical pairing test before saving.",
            'medium' => $summary." Possible match to {$label}. Confirm with a physical pairing test before saving.",
            'low' => $summary." Weak match to {$label}; identifiers alone cannot confirm it. Confirm with a physical pairing test before saving.",
            default => $summary.' Not enough evidence to identify a registered mouse.',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $dongles
     * @param  array<int, array<string, mixed>>  $unattributedDevices
     */
    private function buildMessage(array $dongles, array $unattributedDevices): string
    {
        if ($dongles === []) {
            return 'No receiver candidates were detected. Plug in a dongle and scan again.';
        }

        $deviceCount = array_sum(array_map(fn (array $dongle): int => count($dongle['connected_devices']), $dongles));
        $suggestionCount = array_sum(array_map(fn (array $dongle): int => count($dongle['suggestions']), $dongles));

        return sprintf(
            'Detected %d receiver candidate(s) with %d connected device(s) and %d suggestion(s). %d device(s) could not be attributed to a receiver. Review each suggestion and confirm matches with a physical pairing test.',
            count($dongles),
            $deviceCount,
            $suggestionCount,
            count($unattributedDevices),
        );
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
     * @return array<int, string>
     */
    private function nameTokens(string $value): array
    {
        $tokens = preg_split('/[^a-z0-9]+/', mb_strtolower($value)) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            fn (string $token): bool => mb_strlen($token) >= 2 && ! in_array($token, self::NAME_NOISE, true),
        )));
    }

    /**
     * @param  array<int, string>  $haystack
     * @param  array<int, string>  $needles
     */
    private function containsAllTokens(array $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (! in_array($needle, $haystack, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array<int, string>  $haystack
     * @param  array<int, string>  $needles
     * @return array<int, string>
     */
    private function matchingTokens(array $haystack, array $needles): array
    {
        return array_values(array_intersect($haystack, $needles));
    }

    private function sameBrand(string $first, string $second): bool
    {
        return $first !== '' && $second !== '' && mb_strtolower($first) === mb_strtolower($second);
    }

    private function sameModel(string $first, string $second): bool
    {
        if ($first === '' || $second === '') {
            return false;
        }

        $firstTokens = $this->nameTokens($first);
        $secondTokens = $this->nameTokens($second);

        if ($firstTokens === [] || $secondTokens === []) {
            return mb_strtolower(trim($first)) === mb_strtolower(trim($second));
        }

        return $this->containsAllTokens($firstTokens, $secondTokens)
            || $this->containsAllTokens($secondTokens, $firstTokens);
    }

    /**
     * @return array<string, string>
     */
    private function evidence(string $type, string $detail, string $source): array
    {
        return ['type' => $type, 'detail' => $detail, 'source' => $source];
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

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $scannedAt, string $error): array
    {
        return [
            'scanned_at' => $scannedAt,
            'error' => $error,
            'dongles' => [],
            'unattributed_devices' => [],
            'message' => 'Live detection is unavailable: '.$error,
        ];
    }
}
