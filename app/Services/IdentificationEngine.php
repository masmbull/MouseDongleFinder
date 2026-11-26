<?php

namespace App\Services;

/**
 * Evidence-based receiver identification.
 *
 * Every field is backed by a listed evidence source. Unsupported fields stay null
 * and are reported in `unknowns`; no compatibility is inferred from brand or radio band alone.
 */
class IdentificationEngine
{
    private const LOGITECH_SUPPORT = 'https://support.logi.com/';

    private const LOGITECH_PAIRING_ARTICLE = 'https://support.logi.com/hc/en-us/articles/5470036605975-Unifying-Receiver-Software-Pairing-and-Troubleshooting';

    private const RAZER_SUPPORT = 'https://www.razer.com/support';

    private const MICROSOFT_SUPPORT = 'https://support.microsoft.com/';

    private const USB_IDS_URL = 'https://www.linux-usb.org/usb.ids';

    /** @var array<string, array<string, string>> */
    private const CURATED_RECEIVERS = [
        '046D:C52B' => ['model' => 'Unifying Receiver', 'family' => 'Unifying', 'technology' => '2.4 GHz proprietary (Logitech Unifying)', 'frequency' => '2.4 GHz'],
        '046D:C532' => ['model' => 'Unifying Receiver', 'family' => 'Unifying', 'technology' => '2.4 GHz proprietary (Logitech Unifying)', 'frequency' => '2.4 GHz'],
        '046D:C534' => ['model' => 'Nano Receiver', 'family' => 'Nano', 'technology' => '2.4 GHz proprietary (Logitech Nano)', 'frequency' => '2.4 GHz'],
        '046D:C537' => ['model' => 'Nano Receiver', 'family' => 'Nano', 'technology' => '2.4 GHz proprietary (Logitech Nano)', 'frequency' => '2.4 GHz'],
        '046D:C539' => ['model' => 'Lightspeed Receiver', 'family' => 'Lightspeed', 'technology' => '2.4 GHz proprietary (Logitech Lightspeed)', 'frequency' => '2.4 GHz'],
        '046D:C53A' => ['model' => 'Lightspeed Receiver', 'family' => 'Lightspeed', 'technology' => '2.4 GHz proprietary (Logitech Lightspeed)', 'frequency' => '2.4 GHz'],
        '046D:C53F' => ['model' => 'Lightspeed Receiver', 'family' => 'Lightspeed', 'technology' => '2.4 GHz proprietary (Logitech Lightspeed)', 'frequency' => '2.4 GHz'],
        '046D:C541' => ['model' => 'Lightspeed Receiver', 'family' => 'Lightspeed', 'technology' => '2.4 GHz proprietary (Logitech Lightspeed)', 'frequency' => '2.4 GHz'],
        '046D:C547' => ['model' => 'Lightspeed Receiver', 'family' => 'Lightspeed', 'technology' => '2.4 GHz proprietary (Logitech Lightspeed)', 'frequency' => '2.4 GHz'],
        '046D:C54D' => ['model' => 'Lightspeed Receiver', 'family' => 'Lightspeed', 'technology' => '2.4 GHz proprietary (Logitech Lightspeed)', 'frequency' => '2.4 GHz'],
        '046D:C548' => ['model' => 'Bolt Receiver', 'family' => 'Bolt', 'technology' => '2.4 GHz proprietary (Logitech Bolt)', 'frequency' => '2.4 GHz'],
    ];

    /** @var array<string, array<string, string>> */
    private const CURATED_VENDORS = [
        '046D' => ['manufacturer' => 'Logitech', 'support_url' => self::LOGITECH_SUPPORT, 'pairing_utility' => 'Logi Options+ or Logitech Unifying Software (older receivers)', 'source_url' => self::LOGITECH_PAIRING_ARTICLE],
        '1532' => ['manufacturer' => 'Razer', 'support_url' => self::RAZER_SUPPORT, 'pairing_utility' => 'Razer Synapse', 'source_url' => self::RAZER_SUPPORT],
        '045E' => ['manufacturer' => 'Microsoft', 'support_url' => self::MICROSOFT_SUPPORT, 'pairing_utility' => 'Microsoft Mouse and Keyboard Center', 'source_url' => self::MICROSOFT_SUPPORT],
    ];

    public function __construct(private UsbIdDatabase $usbIds) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function analyze(array $input): array
    {
        $vid = $this->normalizeId($input['vid'] ?? null);
        $pid = $this->normalizeId($input['pid'] ?? null);
        $hardwareId = $this->stringOrNull($input['hardware_id'] ?? null);

        if ($hardwareId !== null) {
            $parsed = $this->parseHardwareId($hardwareId);
            $vid ??= $parsed['vid'];
            $pid ??= $parsed['pid'];
        }

        $detected = $this->compact([
            'vid' => $vid,
            'pid' => $pid,
            'hardware_id' => $hardwareId,
            'device_instance_id' => $this->stringOrNull($input['device_instance_id'] ?? null),
            'serial_number' => $this->stringOrNull($input['serial_number'] ?? null),
            'name' => $this->stringOrNull($input['name'] ?? null),
            'is_receiver' => isset($input['is_receiver']) ? (bool) $input['is_receiver'] : null,
        ]);

        $manual = $this->compact([
            'manufacturer' => $this->stringOrNull($input['manufacturer'] ?? null),
            'model' => $this->stringOrNull($input['model'] ?? null),
            'serial_number' => $this->stringOrNull($input['serial_number'] ?? null),
            'notes' => $this->stringOrNull($input['notes'] ?? null),
            'vid_pid_source' => $this->stringOrNull($input['vid_pid_source'] ?? null),
            'dongle_id' => $this->stringOrNull($input['dongle_id'] ?? null),
        ]);

        $online = $this->normalizeOnlineResults($input['online_results'] ?? []);
        $local = $this->usbIds->lookup($vid, $pid);

        $vendorName = $local['vendor_name'];
        $productName = $local['product_name'];
        $probableManufacturer = null;
        $probableModel = null;
        $deviceCategory = null;
        $wirelessTechnology = null;
        $frequency = null;
        $receiverFamily = null;
        $pairingUtility = null;
        $supportUrl = null;
        $confidence = 'unknown';

        $evidence = [];
        $candidates = [];
        $researchSources = [];
        $recommendedSoftware = [];

        if ($vid !== null || $pid !== null) {
            $evidence[] = $this->evidence(
                'detected',
                'Hardware identifiers reported by the USB scan: '.implode(' ', array_filter([$vid ? "VID $vid" : null, $pid ? "PID $pid" : null])).'.',
                $manual['vid_pid_source'] ?? 'usb_scan',
            );
        }

        if ($local['found']) {
            $detail = $vendorName !== null ? "Vendor $vid resolved as $vendorName" : '';
            if ($productName !== null) {
                $detail .= $detail !== '' ? "; product $pid resolved as $productName" : "Product $vid:$pid resolved as $productName";
            }
            $evidence[] = $this->evidence('local_database', $detail.'.', UsbIdDatabase::USB_IDS_URL);
        }

        $curatedKey = $vid !== null && $pid !== null ? "$vid:$pid" : null;
        $curated = $curatedKey !== null ? (self::CURATED_RECEIVERS[$curatedKey] ?? null) : null;
        $vendorReference = $vid !== null ? (self::CURATED_VENDORS[$vid] ?? null) : null;

        if ($curated !== null) {
            $vendorName ??= $vendorReference['manufacturer'] ?? null;
            $productName = $curated['model'];
            $probableManufacturer = $vendorReference['manufacturer'] ?? $vendorName;
            $probableModel = $curated['model'];
            $deviceCategory = 'wireless receiver';
            $wirelessTechnology = $curated['technology'];
            $frequency = $curated['frequency'];
            $receiverFamily = $curated['family'];
            $pairingUtility = $vendorReference['pairing_utility'] ?? null;
            $supportUrl = $vendorReference['support_url'] ?? null;

            $evidence[] = $this->evidence(
                'curated_reference',
                "$probableManufacturer $probableModel (VID $vid, PID $pid) is documented as the {$curated['family']} receiver family.",
                $vendorReference['source_url'] ?? self::LOGITECH_SUPPORT,
            );
            $candidates[] = $this->candidate(
                $probableManufacturer,
                $probableModel,
                'Exact documented VID:PID match; receiver family is documented. Physical pairing test still required.',
                'high',
                $vendorReference['source_url'] ?? self::LOGITECH_SUPPORT,
            );
            // Only family-level compatibility is asserted, never a specific mouse model.
            $confidence = 'high';
        } elseif ($productName !== null && $pid !== null) {
            $probableManufacturer = $vendorName;
            $probableModel = $productName;
            $deviceCategory = 'USB device';
            $evidence[] = $this->evidence('local_database', "Exact VID:PID $vid:$pid match in the local USB ID table.", UsbIdDatabase::USB_IDS_URL);
            $candidates[] = $this->candidate($vendorName, $productName, 'Exact local VID:PID match; receiver family is not documented.', 'medium', UsbIdDatabase::USB_IDS_URL);
            $confidence = 'medium';
        }

        if ($vendorReference !== null) {
            $probableManufacturer ??= $vendorReference['manufacturer'];
            $pairingUtility ??= $vendorReference['pairing_utility'];
            $supportUrl ??= $vendorReference['support_url'];

            $evidence[] = $this->evidence(
                'curated_reference',
                "Vendor ID $vid is documented as {$vendorReference['manufacturer']}; the exact PID is not in the curated receiver table.",
                $vendorReference['source_url'],
            );

            if ($curated === null) {
                $candidates[] = $this->candidate(
                    $vendorReference['manufacturer'],
                    $productName ?? 'Unspecified receiver',
                    'Vendor identifier only; no documented receiver family for this PID.',
                    'low',
                    $vendorReference['source_url'],
                );
            }

            if ($confidence === 'unknown' || $confidence === 'low') {
                $confidence = 'low';
            }
        } elseif ($vendorName !== null) {
            $probableManufacturer ??= $vendorName;
            if ($confidence === 'unknown') {
                $confidence = 'low';
            }
        }

        if ($manual !== []) {
            $evidence[] = $this->evidence('manual', 'User-entered physical markings were considered.', 'user_input');
            $probableManufacturer ??= $manual['manufacturer'] ?? null;
            $probableModel ??= $manual['model'] ?? null;
        }

        foreach ($online as $source) {
            $researchSources[] = $source;
            $evidence[] = $this->evidence('online', $source['title'], $source['url']);
        }

        if ($supportUrl !== null) {
            $researchSources[] = $this->researchSource(
                ($probableManufacturer ?? 'Manufacturer').' official support',
                $supportUrl,
                $probableManufacturer ?? 'manufacturer',
                true,
                'support',
                'Official manufacturer support and driver/pairing documentation.',
            );
        }

        $researchSources[] = $this->researchSource(
            'USB ID Repository (usb.ids)',
            UsbIdDatabase::USB_IDS_URL,
            'linux-usb.org',
            false,
            'reference',
            'Community-maintained Vendor ID / Product ID name database.',
        );

        $deviceHuntUrl = null;
        if ($vid !== null) {
            $deviceHuntUrl = 'https://devicehunt.com/view/type/usb/vendor/'.$vid.($pid !== null ? '/device/'.$pid : '');
            $researchSources[] = $this->researchSource(
                'DeviceHunt USB lookup',
                $deviceHuntUrl,
                'devicehunt.com',
                false,
                'lookup',
                'Human-readable lookup for the detected VID and PID.',
            );
        }

        $recommendedSoftware = $this->recommendedSoftware($probableManufacturer, $receiverFamily, $deviceHuntUrl);

        if ($curated !== null) {
            $compatibleMice = [[
                'brand' => $probableManufacturer,
                'model' => $receiverFamily.'-compatible mice (verify the individual model)',
                'reason' => 'This receiver family pairs only with explicitly compatible mice. The model list must be checked and the pairing tested physically.',
                'confidence' => 'medium',
                'source_url' => $vendorReference['source_url'] ?? self::LOGITECH_SUPPORT,
            ]];
        } else {
            $compatibleMice = [];
        }

        $unknowns = $this->buildUnknowns([
            'vendor_name' => $vendorName,
            'product_name' => $productName,
            'probable_model' => $probableModel,
            'device_category' => $deviceCategory,
            'wireless_technology' => $wirelessTechnology,
            'frequency' => $frequency,
            'receiver_family' => $receiverFamily,
            'pairing_utility' => $pairingUtility,
            'support_url' => $supportUrl,
        ]);
        $unknowns[] = 'Physical pairing test is still required to confirm which mouse belongs to this receiver.';

        $inferred = $this->compact([
            'probable_manufacturer' => $probableManufacturer,
            'probable_model' => $probableModel,
            'device_category' => $deviceCategory,
            'wireless_technology' => $wirelessTechnology,
            'frequency' => $frequency,
            'receiver_family' => $receiverFamily,
            'pairing_utility' => $pairingUtility,
            'support_url' => $supportUrl,
            'confidence' => $confidence,
        ]);

        return [
            'vid' => $vid,
            'pid' => $pid,
            'vendor_name' => $vendorName,
            'product_name' => $productName,
            'probable_manufacturer' => $probableManufacturer,
            'probable_model' => $probableModel,
            'device_category' => $deviceCategory,
            'wireless_technology' => $wirelessTechnology,
            'frequency' => $frequency,
            'receiver_family' => $receiverFamily,
            'compatible_mice' => $compatibleMice,
            'pairing_utility' => $pairingUtility,
            'support_url' => $supportUrl,
            'confidence' => $confidence,
            'evidence' => $evidence,
            'candidates' => $candidates,
            'recommended_software' => $recommendedSoftware,
            'research_sources' => $researchSources,
            'search_queries' => $this->buildSearchQueries($vid, $pid, $hardwareId, $probableManufacturer, $probableModel ?? $manual['model'] ?? null, $receiverFamily, $deviceHuntUrl),
            'unknowns' => $unknowns,
            'detected' => $detected,
            'manual' => $manual,
            'online' => $online,
            'inferred' => $inferred,
        ];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function compact(array $values): array
    {
        return array_filter($values, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

    private function stringOrNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function normalizeId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[0-9A-F]{4}$/', $value) === 1 ? $value : null;
    }

    /**
     * @return array{vid: string|null, pid: string|null}
     */
    private function parseHardwareId(string $hardwareId): array
    {
        $vid = null;
        $pid = null;

        if (preg_match('/VID[_-]?([0-9A-Fa-f]{4})/', $hardwareId, $matches) === 1) {
            $vid = strtoupper($matches[1]);
        }

        if (preg_match('/PID[_-]?([0-9A-Fa-f]{4})/', $hardwareId, $matches) === 1) {
            $pid = strtoupper($matches[1]);
        }

        return ['vid' => $vid, 'pid' => $pid];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeOnlineResults(mixed $results): array
    {
        if (! is_array($results)) {
            return [];
        }

        $normalized = [];

        foreach ($results as $result) {
            if (! is_array($result)) {
                continue;
            }

            $url = $this->stringOrNull($result['url'] ?? null);

            if ($url === null) {
                continue;
            }

            $normalized[] = $this->researchSource(
                $this->stringOrNull($result['title'] ?? null) ?? $url,
                $url,
                $this->stringOrNull($result['source'] ?? 'online') ?? 'online',
                (bool) ($result['official'] ?? false),
                $this->stringOrNull($result['category'] ?? 'online') ?? 'online',
                $this->stringOrNull($result['snippet'] ?? null),
            );
        }

        return $normalized;
    }

    /**
     * @return array<string, string>
     */
    private function evidence(string $type, string $detail, string $source): array
    {
        return ['type' => $type, 'detail' => $detail, 'source' => $source];
    }

    /**
     * @return array<string, string>
     */
    private function candidate(?string $manufacturer, ?string $model, string $reason, string $confidence, string $sourceUrl): array
    {
        return [
            'manufacturer' => $manufacturer,
            'model' => $model,
            'reason' => $reason,
            'confidence' => $confidence,
            'source_url' => $sourceUrl,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function researchSource(string $title, string $url, string $source, bool $official, string $category, ?string $snippet): array
    {
        return [
            'title' => $title,
            'url' => $url,
            'source' => $source,
            'official' => $official,
            'category' => $category,
            'snippet' => $snippet,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recommendedSoftware(?string $manufacturer, ?string $family, ?string $deviceHuntUrl): array
    {
        $software = [];

        if ($manufacturer === 'Logitech') {
            $software[] = [
                'name' => 'Logi Options+',
                'type' => 'pairing',
                'description' => 'Official Logitech utility for pairing and configuring supported receivers and devices.',
                'url' => self::LOGITECH_SUPPORT,
                'official' => true,
            ];
            $software[] = [
                'name' => 'Logitech Unifying Software',
                'type' => 'pairing',
                'description' => 'Official pairing software and troubleshooting for Unifying receivers.',
                'url' => self::LOGITECH_PAIRING_ARTICLE,
                'official' => true,
            ];
        } elseif ($manufacturer === 'Razer') {
            $software[] = [
                'name' => 'Razer Synapse',
                'type' => 'pairing',
                'description' => 'Official Razer configuration and pairing software.',
                'url' => self::RAZER_SUPPORT,
                'official' => true,
            ];
        } elseif ($manufacturer === 'Microsoft') {
            $software[] = [
                'name' => 'Microsoft Mouse and Keyboard Center',
                'type' => 'driver',
                'description' => 'Official Microsoft utility for supported mice, keyboards and receivers.',
                'url' => self::MICROSOFT_SUPPORT,
                'official' => true,
            ];
        }

        if ($deviceHuntUrl !== null) {
            $software[] = [
                'name' => 'USB VID/PID lookup',
                'type' => 'lookup',
                'description' => 'Community lookup for the detected hardware identifiers.',
                'url' => $deviceHuntUrl,
                'official' => false,
            ];
        }

        return $software;
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<int, string>
     */
    private function buildUnknowns(array $fields): array
    {
        $messages = [
            'vendor_name' => 'The USB vendor name is not available offline for this VID.',
            'product_name' => 'The USB product name is not available offline for this VID:PID.',
            'probable_model' => 'The exact receiver model is not documented for these identifiers.',
            'device_category' => 'The device category could not be established.',
            'wireless_technology' => 'The wireless technology could not be established.',
            'frequency' => 'The operating frequency could not be established.',
            'receiver_family' => 'The receiver family could not be established.',
            'pairing_utility' => 'No official pairing utility is documented for this device.',
            'support_url' => 'No official manufacturer support URL is known for this device.',
        ];

        $unknowns = [];

        foreach ($messages as $field => $message) {
            if ($fields[$field] === null) {
                $unknowns[] = $message;
            }
        }

        return $unknowns;
    }

    /**
     * @return array<int, string>
     */
    private function buildSearchQueries(?string $vid, ?string $pid, ?string $hardwareId, ?string $manufacturer, ?string $model, ?string $family, ?string $deviceHuntUrl): array
    {
        $identifier = $vid !== null && $pid !== null ? "$vid:$pid" : ($vid ?? $hardwareId ?? 'unknown receiver');
        $subject = trim(($manufacturer ?? 'USB wireless receiver').' '.($model ?? '').' '.($family !== null ? $family.' receiver' : ''));
        $queries = [
            '"'.$identifier.'" USB VID PID',
        ];

        if ($vid !== null && $pid !== null) {
            $queries[] = '"USB\\VID_'.$vid.'&PID_'.$pid.'" hardware ID';
        }

        $queries[] = trim($manufacturer.' '.$vid.' '.$pid.' receiver identification');
        $queries[] = $subject.' compatible mice list';
        $queries[] = '"FCC ID" '.$subject;
        $queries[] = $subject.' pairing utility software';
        $queries[] = $subject.' driver Windows';
        $queries[] = $subject.' user manual PDF';

        if ($deviceHuntUrl !== null) {
            $queries[] = $deviceHuntUrl;
        }

        $queries = array_values(array_unique(array_filter($queries, fn (string $query) => trim($query) !== '')));

        return array_slice($queries, 0, 10);
    }
}
