<?php

namespace App\Services;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * One-click, evidence-based auto-discovery of mouse↔dongle relationships.
 *
 * The service combines the Dongle Lab receiver scan with the fast live HID scan,
 * attributes each connected mouse to a receiver by Windows VID/PID or hardware
 * IDs, resolves the model from real local data, and saves only *suspected*
 * matches. No physical pairing test is performed and nothing is invented when
 * the scanners are unavailable or the identifiers are ambiguous.
 */
class AutoDiscoverService
{
    /** Words that carry no model information in a Windows device name. */
    private const NAME_NOISE = [
        'usb', 'wireless', 'mouse', 'receiver', 'dongle', 'hid', 'compliant', 'device',
        'optical', 'gaming', 'bluetooth', 'input', 'pointing', 'the', 'and', 'for', 'with',
        'logitech', 'razer', 'microsoft', 'steelseries', 'corsair',
    ];

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

    private const RECEIVER_NAME_PATTERN = '/receiver|dongle|unifying|nano|lightspeed|bolt|slipstream|hyperspeed/i';

    public function __construct(
        private DongleLabService $dongleLab,
        private LivePairingDetector $detector,
    ) {}

    /**
     * @return array{
     *     scanned_at: string,
     *     error: string|null,
     *     message: string,
     *     discovered: array<int, array<string, mixed>>,
     *     unattributed_devices: array<int, array<string, mixed>>
     * }
     */
    public function discover(): array
    {
        $scannedAt = now()->toIso8601String();

        try {
            $dongleScan = $this->dongleLab->scanDongles();
            $liveScan = $this->detector->scanOnly();
        } catch (Throwable $exception) {
            Log::error('Auto-discovery scan failed.', ['message' => $exception->getMessage()]);

            return $this->errorResult($scannedAt, 'Auto-discovery scan failed: '.$exception->getMessage());
        }

        if (! is_array($dongleScan) || ! is_array($liveScan)) {
            return $this->errorResult($scannedAt, 'Auto-discovery received an invalid scanner response.');
        }

        $error = $this->stringOrNull($dongleScan['error'] ?? null)
            ?? $this->stringOrNull($liveScan['error'] ?? null);

        if ($error !== null) {
            return $this->errorResult($scannedAt, $error);
        }

        $scannedAt = $this->stringOrNull($dongleScan['scanned_at'] ?? null) ?? $scannedAt;
        $dongles = is_array($dongleScan['dongles'] ?? null)
            ? array_values(array_filter($dongleScan['dongles'], 'is_array'))
            : [];
        $liveDevices = is_array($liveScan['devices'] ?? null)
            ? array_values(array_filter($liveScan['devices'], 'is_array'))
            : [];
        $mouseDevices = $this->dongleLab->mouseDevices($liveDevices);

        // Windows can report the same receiver under several interface/location
        // entries. A physical dongle is identified by VID:PID here, so collapse
        // duplicates before attribution; likewise for identical HID instance IDs.
        $seenDongles = [];
        $dongles = array_values(array_filter($dongles, function (array $dongle) use (&$seenDongles): bool {
            $port = $this->stringOrNull($dongle['port'] ?? null);

            if ($port === null) {
                $location = $this->stringOrNull($dongle['location'] ?? null) ?? '';
                $port = preg_match('/Port[_#\s]*#?(\d+)/i', $location, $matches) === 1
                    ? 'Port_#'.str_pad($matches[1], 4, '0', STR_PAD_LEFT)
                    : $location;
            }

            $key = $this->normalizeId($dongle['vid'] ?? null).':'.$this->normalizeId($dongle['pid'] ?? null).'|'.$port;

            if (isset($seenDongles[$key])) {
                return false;
            }

            $seenDongles[$key] = true;

            return true;
        }));

        $seenDevices = [];
        $mouseDevices = array_values(array_filter($mouseDevices, function (array $device) use (&$seenDevices): bool {
            $key = $this->stringOrNull($device['instance_id'] ?? null)
                ?? md5(json_encode($device) ?: '');

            if (isset($seenDevices[$key])) {
                return false;
            }

            $seenDevices[$key] = true;

            return true;
        }));

        $discovered = [];
        $unattributedDevices = [];

        foreach ($mouseDevices as $device) {
            $attribution = $this->dongleLab->attributeMouse($device, $dongles);
            $candidates = is_array($attribution['candidates'] ?? null) ? $attribution['candidates'] : [];

            if ($candidates === []) {
                $unattributedDevices[] = $this->publicDevice($device);

                continue;
            }

            $identity = $this->identityFor($device);
            $isGenericName = $this->isGenericName($this->stringOrNull($device['name'] ?? null) ?? '');
            $isAmbiguous = count($candidates) > 1;
            // Even a generic Windows label is real evidence of a connection.
            // Register the pair as "suspected" so the user has something concrete
            // to review/rename, but never claim a confirmed model identity.
            $canAutoCreate = ! $isAmbiguous;

            foreach ($candidates as $candidate) {
                $dongleEntry = $this->dongleEntryForCandidate($dongles, $candidate);
                $evidence = array_merge(
                    is_array($candidate['evidence'] ?? null) ? $candidate['evidence'] : [],
                    $identity['evidence'],
                );

                $dongleResult = [
                    'vid' => $this->normalizeId($candidate['vid'] ?? null),
                    'pid' => $this->normalizeId($candidate['pid'] ?? null),
                    'name' => $this->stringOrNull($dongleEntry['name'] ?? null)
                        ?? 'USB Receiver '.($candidate['vid'] ?? '').':'.($candidate['pid'] ?? ''),
                    'port' => $this->stringOrNull($dongleEntry['port'] ?? null),
                    'dongle_id' => is_int($candidate['dongle_id'] ?? null) ? $candidate['dongle_id'] : null,
                    'auto_created' => false,
                ];
                $mouseResult = null;
                $matchResult = null;

                if ($canAutoCreate) {
                    [$dongle, $dongleCreated] = $this->ensureDongle($dongleResult, $dongleEntry, $identity);
                    $dongleResult['dongle_id'] = $dongle->id;
                    $dongleResult['auto_created'] = $dongleCreated;

                    [$mouse, $mouseCreated] = $this->ensureMouse($identity, $device);
                    $mouseResult = [
                        'id' => $mouse->id,
                        'mouse_id' => $mouse->mouse_id,
                        'brand' => $mouse->brand,
                        'model' => $mouse->model,
                        'photo_url' => $mouse->photo_url,
                        'auto_created' => $mouseCreated,
                    ];

                    $match = $this->activeMatch($mouse->id, $dongle->id);

                    if ($match === null) {
                        $match = DeviceMatch::create([
                            'mouse_id' => $mouse->id,
                            'dongle_id' => $dongle->id,
                            'status' => 'suspected',
                            'notes' => $this->matchNotes($device, $evidence)
                                .(in_array($identity['confidence'], ['low', 'unknown'], true)
                                    ? ' Needs verification: Windows did not expose a reliable model name.'
                                    : ''),
                            'matched_at' => null,
                            'is_seed' => false,
                        ]);
                    }

                    $matchResult = [
                        'id' => $match->id,
                        'status' => $match->status,
                        'notes' => $match->notes,
                    ];
                }

                $discovered[] = [
                    'dongle' => $dongleResult,
                    'device' => $this->publicDevice($device),
                    'identity' => [
                        'brand' => $identity['brand'],
                        'model' => $identity['model'],
                        'source' => $identity['source'],
                        'confidence' => $identity['confidence'],
                    ],
                    'mouse' => $mouseResult,
                    'match' => $matchResult,
                    'evidence' => array_values($evidence),
                    'needs_user_review' => $isAmbiguous
                        || $isGenericName
                        || in_array($identity['confidence'], ['low', 'unknown'], true),
                ];
            }
        }

        return [
            'scanned_at' => $scannedAt,
            'error' => null,
            'message' => $this->buildMessage($discovered, $unattributedDevices),
            'discovered' => $discovered,
            'unattributed_devices' => $unattributedDevices,
        ];
    }

    /**
     * Resolve a mouse identity using only local, real evidence.
     *
     * @param  array<string, mixed>  $device
     * @return array{brand: string|null, model: string|null, source: string, confidence: string, evidence: array<int, array<string, string>>}
     */
    private function identityFor(array $device): array
    {
        $name = $this->stringOrNull($device['name'] ?? null) ?? 'Unknown Device';
        $deviceTokens = $this->nameTokens($name);
        $vid = $this->normalizeId($device['vid'] ?? null);
        $pid = $this->normalizeId($device['pid'] ?? null);
        $isGenericName = $this->isGenericName($name);

        $registeredMice = Mouse::query()
            ->where(function ($query): void {
                $query->whereNull('notes')
                    ->orWhere('notes', 'not like', 'Auto-discovered%');
            })
            ->orderBy('id')
            ->get()
            ->all();
        $profiles = $this->mouseProfiles();
        $vendorName = $this->vendorName($vid);
        $productName = $this->productName($vid, $pid);

        foreach ($registeredMice as $mouse) {
            $brand = $this->stringOrNull($mouse->brand);
            $model = $this->stringOrNull($mouse->model);

            if ($model === null || $this->isGenericName($model)) {
                continue;
            }

            $detail = $this->nameMatchDetail($name, $deviceTokens, $model);

            if ($detail !== null) {
                return $this->identity($brand, $model, 'registered_mouse', 'high', [
                    $this->evidence('device_name', $detail, 'windows_pnp'),
                ]);
            }
        }

        foreach ($profiles as $profile) {
            $brand = $this->stringOrNull($profile['brand'] ?? null);
            $model = $this->stringOrNull($profile['model'] ?? null);
            $detail = $this->nameMatchDetail($name, $deviceTokens, $model);

            if ($detail !== null) {
                return $this->identity($brand, $model, 'device_name', 'high', [
                    $this->evidence('device_name', $detail, 'windows_pnp'),
                    $this->evidence('mouse_profile', "Mouse profile '{$brand} {$model}' is present in the local reference catalogue.", 'mouse_profiles'),
                ]);
            }
        }

        if ($productName !== null && ! $this->isReceiverName($productName)) {
            $productBrand = $vendorName ?? $this->profileBrandForPair($profiles, $vid, $pid);

            if (! $isGenericName && $this->nameMatchDetail($name, $deviceTokens, $productName) !== null) {
                return $this->identity($productBrand, $productName, 'usb_products', 'high', [
                    $this->evidence('usb_product', "Device name '{$name}' matches usb_products name '{$productName}' for VID:PID {$vid}:{$pid}.", 'usb_products'),
                ]);
            }

            if ($isGenericName) {
                return $this->identity($productBrand, $productName, 'usb_products', 'medium', [
                    $this->evidence('usb_product', "Windows reports a generic name; usb_products names VID:PID {$vid}:{$pid} as '{$productName}'.", 'usb_products'),
                ]);
            }
        }

        foreach ($profiles as $profile) {
            $profileVid = $this->normalizeId($profile['vid'] ?? null);
            $profilePid = $this->normalizeId($profile['pid'] ?? null);

            if ($vid !== null && $pid !== null && $profileVid === $vid && $profilePid === $pid) {
                $brand = $this->stringOrNull($profile['brand'] ?? null);
                $model = $this->stringOrNull($profile['model'] ?? null);
                $hint = $isGenericName ? 'Windows reports a generic name' : 'No model was readable from the device name';

                return $this->identity($brand, $model, 'mouse_profile', 'medium', [
                    $this->evidence('mouse_profile', "{$hint}; mouse_profiles matches VID:PID {$vid}:{$pid} to '{$brand} {$model}'.", 'mouse_profiles'),
                ]);
            }
        }

        foreach ($registeredMice as $mouse) {
            $brand = $this->stringOrNull($mouse->brand);
            $model = $this->stringOrNull($mouse->model);

            if ($model === null || $this->isGenericName($model)) {
                continue;
            }

            $modelTokens = $this->nameTokens($model ?? '');
            $matchedTokens = array_values(array_intersect($deviceTokens, $modelTokens));

            if ($matchedTokens !== []) {
                return $this->identity($brand, $model, 'registered_mouse', 'low', [
                    $this->evidence('device_name', "Device name '{$name}' shares model token(s) ".implode(', ', $matchedTokens)." with registered mouse '{$brand} {$model}'.", 'windows_pnp'),
                ]);
            }
        }

        if ($vendorName !== null) {
            return $this->identity($vendorName, null, 'vendor_only', 'low', [
                $this->evidence('usb_vendor', "VID {$vid} resolves to vendor '{$vendorName}'; no model clue is available.", 'usb_vendors'),
            ]);
        }

        return $this->identity(null, null, 'unknown', 'unknown', [
            $this->evidence('unknown', "No local reference identifies device '{$name}' (VID:PID ".($vid ?? '?').':'.($pid ?? '?').').', 'windows_pnp'),
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $profiles
     */
    private function profileBrandForPair(array $profiles, ?string $vid, ?string $pid): ?string
    {
        if ($vid === null || $pid === null) {
            return null;
        }

        foreach ($profiles as $profile) {
            if ($this->normalizeId($profile['vid'] ?? null) === $vid
                && $this->normalizeId($profile['pid'] ?? null) === $pid) {
                return $this->stringOrNull($profile['brand'] ?? null);
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $deviceTokens
     */
    private function nameMatchDetail(string $name, array $deviceTokens, ?string $model): ?string
    {
        if ($model === null || $model === '') {
            return null;
        }

        if (mb_stripos($name, $model) !== false) {
            return "Windows device name '{$name}' contains the full model '{$model}'.";
        }

        $modelTokens = $this->nameTokens($model);

        if ($modelTokens === []) {
            return null;
        }

        foreach ($modelTokens as $token) {
            if (! in_array($token, $deviceTokens, true)) {
                return null;
            }
        }

        return "Windows device name '{$name}' contains all distinctive model tokens '{$model}'.";
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
            DB::table('mouse_profiles')->orderBy('id')->get()->all(),
        );
    }

    private function vendorName(?string $vid): ?string
    {
        if ($vid === null) {
            return null;
        }

        if (Schema::hasTable('usb_vendors')) {
            $name = DB::table('usb_vendors')->where('vid', $vid)->value('vendor_name');
            $name = $this->stringOrNull($name);

            if ($name !== null) {
                return $name;
            }
        }

        return self::VENDOR_BRANDS[$vid] ?? null;
    }

    private function productName(?string $vid, ?string $pid): ?string
    {
        if ($vid === null || $pid === null || ! Schema::hasTable('usb_products')) {
            return null;
        }

        return $this->stringOrNull(
            DB::table('usb_products')->where('vid', $vid)->where('pid', $pid)->value('product_name'),
        );
    }

    /**
     * @param  array<string, mixed>  $dongles
     * @param  array<string, mixed>  $candidate
     * @return array<string, mixed>
     */
    private function dongleEntryForCandidate(array $dongles, array $candidate): array
    {
        $key = $this->stringOrNull($candidate['dongle_key'] ?? null);

        foreach ($dongles as $dongle) {
            if ($key !== null && $this->stringOrNull($dongle['key'] ?? null) === $key) {
                return $dongle;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $dongleResult
     * @param  array<string, mixed>  $dongleEntry
     * @return array{Dongle, bool}
     */
    private function ensureDongle(array $dongleResult, array $dongleEntry, array $identity): array
    {
        $vid = $this->normalizeId($dongleResult['vid'] ?? null);
        $pid = $this->normalizeId($dongleResult['pid'] ?? null);
        $receiverFamily = $this->stringOrNull($dongleEntry['receiver_family'] ?? null);

        $dongle = Dongle::query()->firstOrCreate(
            ['vid' => $vid, 'pid' => $pid],
            [
                'dongle_id' => 'DG-'.strtoupper(Str::random(8)),
                'brand' => $this->stringOrNull($identity['brand'] ?? null),
                'model' => $receiverFamily ?? ($vid !== null && $pid !== null ? $vid.':'.$pid : null),
                'receiver_type' => $receiverFamily,
                'notes' => 'Auto-discovered from Windows PnP on '.now()->toDateString(),
                'is_seed' => false,
            ],
        );

        return [$dongle, $dongle->wasRecentlyCreated];
    }

    /**
     * @param  array<string, mixed>  $identity
     * @param  array<string, mixed>  $device
     * @return array{Mouse, bool}
     */
    private function ensureMouse(array $identity, array $device): array
    {
        $brand = $this->stringOrNull($identity['brand'] ?? null) ?? 'Unknown';
        $model = $this->stringOrNull($identity['model'] ?? null)
            ?? $this->stringOrNull($device['name'] ?? null)
            ?? 'Unknown Device';

        // Generic Windows labels are not enough to recognise a physical mouse.
        // Keep the records distinguishable so each receiver gets its own pair.
        if ($this->isGenericName($model)) {
            $vid = $this->normalizeId($device['vid'] ?? null);
            $pid = $this->normalizeId($device['pid'] ?? null);
            $model = $model.' ('.($vid ?? '?').':'.($pid ?? '?').')';
        }

        foreach (Mouse::query()->orderBy('id')->get() as $existingMouse) {
            if ($this->normalized($existingMouse->brand) === $this->normalized($brand)
                && $this->normalized($existingMouse->model) === $this->normalized($model)) {
                return [$existingMouse, false];
            }
        }

        $mouse = Mouse::query()->firstOrCreate(
            ['brand' => $brand, 'model' => $model],
            [
                'mouse_id' => 'MS-'.strtoupper(Str::random(8)),
                'notes' => sprintf(
                    'Auto-discovered from Windows HID device %s at %s',
                    $this->stringOrNull($device['name'] ?? null) ?? 'Unknown Device',
                    now()->toIso8601String(),
                ),
                'is_seed' => false,
            ],
        );

        return [$mouse, $mouse->wasRecentlyCreated];
    }

    private function activeMatch(int $mouseId, int $dongleId): ?DeviceMatch
    {
        return DeviceMatch::query()
            ->where(function ($query) use ($mouseId, $dongleId) {
                $query->where('mouse_id', $mouseId)
                    ->orWhere('dongle_id', $dongleId);
            })
            ->whereIn('status', ['matched', 'suspected'])
            ->first();
    }

    /**
     * @param  array<string, mixed>  $device
     * @param  array<int, array<string, string>>  $evidence
     */
    private function matchNotes(array $device, array $evidence): string
    {
        $details = [];

        foreach ($evidence as $item) {
            $detail = $this->stringOrNull($item['detail'] ?? null);

            if ($detail !== null) {
                $details[] = rtrim($detail, '.');
            }
        }

        $summary = $details === [] ? 'Windows PnP identifiers only.' : implode('; ', $details).'.';

        return sprintf(
            "Auto-discovered from Windows HID device '%s'. Evidence: %s No physical pairing test was performed.",
            $this->stringOrNull($device['name'] ?? null) ?? 'Unknown Device',
            $summary,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $discovered
     * @param  array<int, array<string, mixed>>  $unattributedDevices
     */
    private function buildMessage(array $discovered, array $unattributedDevices): string
    {
        if ($discovered === [] && $unattributedDevices === []) {
            return 'No mouse-like devices are connected. Switch on one mouse at a time, then run auto-discover again.';
        }

        $needsReview = count(array_filter(
            $discovered,
            fn (array $entry): bool => (bool) $entry['needs_user_review'],
        ));

        return sprintf(
            'Auto-discovered %d mouse/dongle relationship(s) from Windows PnP evidence. %d result(s) need user review and %d mouse device(s) could not be attributed to a dongle. All matches are suspected only; confirm each one with a physical pairing test.',
            count($discovered),
            $needsReview,
            count($unattributedDevices),
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
            'vid' => $this->normalizeId($device['vid'] ?? null),
            'pid' => $this->normalizeId($device['pid'] ?? null),
            'instance_id' => $this->stringOrNull($device['instance_id'] ?? $device['device_instance_id'] ?? null) ?? '',
            'class' => $this->stringOrNull($device['class'] ?? $device['usb_class'] ?? null) ?? '',
            'status' => $this->stringOrNull($device['status'] ?? null) ?? '',
            'hardware_ids' => is_array($device['hardware_ids'] ?? null)
                ? array_values(array_filter(array_map(fn (mixed $id): ?string => $this->stringOrNull($id), $device['hardware_ids'])))
                : [],
            'location' => $this->stringOrNull($device['location'] ?? null) ?? '',
        ];
    }

    /**
     * @param  array<int, array<string, string>>  $evidence
     * @return array{brand: string|null, model: string|null, source: string, confidence: string, evidence: array<int, array<string, string>>}
     */
    private function identity(?string $brand, ?string $model, string $source, string $confidence, array $evidence): array
    {
        return [
            'brand' => $brand,
            'model' => $model,
            'source' => $source,
            'confidence' => $confidence,
            'evidence' => $evidence,
        ];
    }

    private function isGenericName(string $name): bool
    {
        if ($this->nameTokens($name) === []) {
            return true;
        }

        $normalized = preg_replace('/[^a-z0-9]+/', ' ', mb_strtolower($name)) ?? '';

        return in_array(trim($normalized), [
            'hid compliant mouse', 'hid compliant device', 'hid compliant pointing device',
            'hid compliant input device', 'usb mouse', 'mouse', 'mouse device',
            'pointing device', 'hid device', 'unknown device', 'wireless mouse',
            'optical mouse', 'usb input device',
        ], true);
    }

    private function isReceiverName(string $name): bool
    {
        return preg_match(self::RECEIVER_NAME_PATTERN, $name) === 1;
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
     * @return array{scanned_at: string, error: string, message: string, discovered: array<int, array<string, mixed>>, unattributed_devices: array<int, array<string, mixed>>}
     */
    private function errorResult(string $scannedAt, string $error): array
    {
        return [
            'scanned_at' => $scannedAt,
            'error' => $error,
            'message' => 'Auto-discovery is unavailable: '.$error,
            'discovered' => [],
            'unattributed_devices' => [],
        ];
    }

    /**
     * @return array{type: string, detail: string, source: string}
     */
    private function evidence(string $type, string $detail, string $source): array
    {
        return ['type' => $type, 'detail' => $detail, 'source' => $source];
    }

    private function normalized(?string $value): string
    {
        return trim(mb_strtolower($value ?? ''));
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
