<?php

namespace Database\Seeders;

use App\Models\DeviceMatch;
use App\Models\DevicePhoto;
use App\Models\Dongle;
use App\Models\Identification;
use App\Models\Mouse;
use App\Models\MouseProfile;
use App\Models\UsbProduct;
use App\Models\UsbVendor;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * DEVELOPMENT SAMPLE DATA ONLY - A GLOBAL REFERENCE CATALOGUE, NOT AN OFFICE INVENTORY.
 *
 * Every row is flagged as a sample where the schema supports `is_seed`. The catalogue is
 * public, researched product/pairing information. No USB scan, no OCR result and no physical
 * pairing test is fabricated here.
 *
 * Sources actually consulted (2026-11-22):
 *   - Local USB ID Repository snapshot: storage/app/usbids/usb.ids, version 2026.06.26
 *     (upstream https://www.linux-usb.org/usb.ids ; https://www.linux-usb.org/usb-ids.html).
 *     ALL vendors and all product rows whose name matches
 *     mouse|receiver|dongle|wireless|transceiver|trackball|bluetooth|hid|keyboard|pointing
 *     are imported verbatim from that snapshot into usb_vendors / usb_products. The generated
 *     data file database/seeders/usb-ids-snapshot.php was produced by a temporary parse script
 *     kept outside the repository.
 *     usb.ids data - Copyright (c) Stephen J. Gowdy and contributors, linux-usb.org; BSD-3 option.
 *   - DeviceHunt mirror of usb.ids: https://devicehunt.com/view/type/usb/vendor/046D/device/C52B ,
 *     https://devicehunt.com/view/type/usb/vendor/1532/device/007B (fetched and verified).
 *   - Logitech: Unifying overview https://en.wikipedia.org/wiki/Logitech_Unifying_receiver ;
 *     pairing support https://support.logi.com/hc/en-us/articles/5470036605975-Unifying-Receiver-Software-Pairing-and-Troubleshooting ;
 *     Logi Options+ https://www.logitech.com/optionsplus ;
 *     Logi Bolt technology https://www.logitech.com/en-us/business/work-setups/logi-bolt-wireless-technology.html ;
 *     Bolt vs Unifying https://support.logi.com/hc/en-us/articles/1500012483162
 *   - Razer HyperSpeed (2.4 GHz) https://www.razer.com/technology/razer-hyperspeed-wireless ;
 *     Razer Synapse https://www.razer.com/synapse-3
 *   - CORSAIR SLIPSTREAM (2.4 GHz) https://www.corsair.com/us/en/s/slipstream ; iCUE https://www.corsair.com/us/en/s/icue
 *   - SteelSeries GG https://steelseries.com/gg ; Rival 3 Wireless (2.4 GHz + BT5.0) https://steelseries.com/gaming-mice
 *   - ASUS ROG SpeedNova (2.4 GHz) https://rog.asus.com/content/rog-speednova-wireless-technology ; support https://www.asus.com/support/
 *   - Official product/support pages for every other brand are recorded in the `source` and
 *     `official_support_url` fields of each mouse profile (see mouseProfileRows()).
 *
 * Honesty rules applied:
 *   - VID/PID is only filled when usb.ids documents it; otherwise it stays null.
 *   - A receiver PID is never presented as the mouse's own PID.
 *   - 1EA7 is SHARKOON Technologies in this usb.ids snapshot, while the product string for
 *     1EA7:0030 names a Trust GXT 158 Orna mouse; both facts are recorded in the notes.
 *   - C53A/C53F/C541/C547 could not be confirmed as Logitech receivers in this snapshot and
 *     are deliberately absent.
 *   - `high` confidence requires a documented VID and PID; brand-known dongles without an
 *     identifier are `medium`; genuinely unknown dongles get no identification at all.
 *   - 2.4 GHz ISM / Bluetooth band statements are documented radio bands, not claimed channels.
 */
class DatabaseSeeder extends Seeder
{
    private const USB_IDS = 'https://www.linux-usb.org/usb.ids';

    private const LOGITECH_UNIFYING_ARTICLE = 'https://support.logi.com/hc/en-us/articles/5470036605975-Unifying-Receiver-Software-Pairing-and-Troubleshooting';

    private const LOGI_OPTIONS = 'https://www.logitech.com/optionsplus';

    private const LOGI_BOLT = 'https://www.logitech.com/en-us/business/work-setups/logi-bolt-wireless-technology.html';

    private const DEVICEHUNT = 'https://devicehunt.com/view/type/usb/vendor/';

    private const WIKI_UNIFYING = 'https://en.wikipedia.org/wiki/Logitech_Unifying_receiver';

    private const WIKI_BLUETOOTH = 'https://en.wikipedia.org/wiki/Bluetooth';

    private const RAZER_SYNAPSE = 'https://www.razer.com/synapse-3';

    private const RAZER_HYPERSPEED = 'https://www.razer.com/technology/razer-hyperspeed-wireless';

    private const CORSAIR_ICUE = 'https://www.corsair.com/us/en/s/icue';

    private const CORSAIR_SLIPSTREAM = 'https://www.corsair.com/us/en/s/slipstream';

    private const STEELSERIES_GG = 'https://steelseries.com/gg';

    private const ASUS_SPEEDNOVA = 'https://rog.asus.com/content/rog-speednova-wireless-technology';

    private const TECH_24 = 'Proprietary 2.4 GHz';

    private const TECH_DUAL = 'Proprietary 2.4 GHz / Bluetooth LE';

    private const TECH_BT = '2.4 GHz ISM (Bluetooth)';

    private const FREQ_24 = '2.4 GHz ISM';

    private const FREQ_BT = '2.4 GHz ISM (Bluetooth)';

    private const UTILITY_OS_BT = 'Operating-system Bluetooth settings';

    private const UTILITY_NONE = 'No separate pairing utility documented';

    /** @var array{vendors: array<string, string>, products: array<int, array{vid: string, pid: string, product_name: string}>}|null */
    private ?array $usbSnapshot = null;

    /** @var array{devices_with_photos: int, total_photos: int, devices_without_photos: int, missing_files: int} */
    private array $photoCounts = [
        'devices_with_photos' => 0,
        'total_photos' => 0,
        'devices_without_photos' => 0,
        'missing_files' => 0,
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('The development sample seeder must not run in production.');
        }

        $this->seedUsbIdentityTables();
        $this->seedMouseProfiles();
        $this->seedMice();
        $this->seedDongles();
        $this->seedIdentifications();
        $this->seedDeviceMatches();
        $this->seedDevicePhotos();
        $this->reportCounts();
    }

    private function seedUsbIdentityTables(): void
    {
        $snapshot = $this->usbSnapshot();

        $vendors = $snapshot['vendors'];
        foreach ($this->usbVendors() as $vid => $vendorName) {
            $vendors[$vid] = $vendorName;
        }

        foreach (array_chunk($vendors, 500, true) as $chunk) {
            $rows = [];
            foreach ($chunk as $vid => $vendorName) {
                $rows[] = [
                    'vid' => $vid,
                    'vendor_name' => $vendorName,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
            UsbVendor::query()->insertOrIgnore($rows);
        }

        $products = [];
        foreach ($this->usbProducts() as $product) {
            $products[$product['vid'].':'.$product['pid']] = $product;
        }
        foreach ($snapshot['products'] as $product) {
            $products[$product['vid'].':'.$product['pid']] = $product;
        }

        foreach (array_chunk(array_values($products), 500) as $chunk) {
            $rows = array_map(static fn (array $product): array => $product + [
                'created_at' => now(),
                'updated_at' => now(),
            ], $chunk);
            UsbProduct::query()->insertOrIgnore($rows);
        }
    }

    private function seedMouseProfiles(): void
    {
        foreach ($this->mouseProfileRows() as $profile) {
            MouseProfile::updateOrCreate(
                ['brand' => $profile['brand'], 'model' => $profile['model']],
                $profile,
            );
        }
    }

    private function seedMice(): void
    {
        foreach ($this->mouseRows() as $row) {
            Mouse::firstOrCreate(
                ['mouse_id' => $row['mouse_id']],
                array_merge($row, ['is_seed' => true]),
            );
        }
    }

    private function seedDongles(): void
    {
        foreach ($this->dongleRows() as $row) {
            Dongle::firstOrCreate(
                ['dongle_id' => $row['dongle_id']],
                array_merge($row, ['is_seed' => true]),
            );
        }
    }

    private function seedIdentifications(): void
    {
        $meta = $this->identificationMeta();

        foreach ($this->dongleRows() as $dongle) {
            if ($dongle['brand'] === null) {
                continue;
            }

            $dongleId = $this->dongleBySeedId($dongle['dongle_id'])->id;
            $dongleMeta = $meta[$dongle['dongle_id']] ?? [];
            $vid = $dongle['vid'];
            $pid = $dongle['pid'];

            $payload = $this->identificationData(
                $vid,
                $pid,
                $dongle['brand'],
                $dongle['model'] ?? 'Wireless receiver',
                $dongleMeta['technology'] ?? self::TECH_24,
                $dongleMeta['frequency'] ?? self::FREQ_24,
                $dongleMeta['family'] ?? $dongle['receiver_type'] ?? 'Generic 2.4 GHz',
                $this->compatibleMiceForBrand($dongle['brand']),
                $dongleMeta['utility'] ?? self::UTILITY_NONE,
                $dongleMeta['support_url'] ?? self::USB_IDS,
                $vid !== null && $pid !== null ? 'high' : 'medium',
                $dongleMeta['notes'] ?? 'Documentation-level lead; a physical pairing test is still required.',
                $dongleMeta['extra_sources'] ?? [],
            );

            Identification::firstOrCreate([
                'dongle_id' => $dongleId,
                'vid' => $vid,
                'pid' => $pid,
            ], array_merge($payload, ['is_seed' => true]));
        }
    }

    private function seedDeviceMatches(): void
    {
        foreach ($this->matchRows() as $row) {
            $mouse = $this->mouseBySeedId($row['mouse_id']);
            $dongle = $this->dongleBySeedId($row['dongle_id']);

            DeviceMatch::firstOrCreate([
                'mouse_id' => $mouse->id,
                'dongle_id' => $dongle->id,
            ], [
                'status' => $row['status'],
                'notes' => $row['notes'],
                'matched_at' => now()->subDays($row['days_ago']),
                'is_seed' => true,
            ]);
        }
    }

    /**
     * Creates reference-photo rows from the curated manifest of images that were downloaded into
     * public/images/devices/ during development. No network call is made while seeding: the app must
     * seed offline and deterministically. Only exact model photos are included; absence is honest.
     */
    private function seedDevicePhotos(): void
    {
        $manifestPath = __DIR__.'/device-photos-resolved.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (! is_array($manifest)) {
            throw new RuntimeException('The device photo manifest could not be read.');
        }

        $totalPhotos = 0;
        $devicesWithPhotos = 0;
        $missingFiles = 0;

        foreach ($manifest as $seedId => $photos) {
            $isMouse = str_starts_with((string) $seedId, 'MS-');
            $device = $isMouse
                ? Mouse::where('mouse_id', $seedId)->first()
                : Dongle::where('dongle_id', $seedId)->first();

            if ($device === null) {
                continue;
            }

            $createdForDevice = 0;
            foreach ($photos as $photo) {
                $path = (string) $photo['path'];
                if (! file_exists(public_path($path))) {
                    $missingFiles++;

                    continue;
                }

                DevicePhoto::firstOrCreate([
                    'photoable_type' => $device->getMorphClass(),
                    'photoable_id' => $device->id,
                    'path' => $path,
                ], [
                    'photo_type' => $photo['photo_type'],
                    'caption' => $photo['caption'],
                    'source_url' => $photo['source_url'],
                    'source_name' => $photo['source_name'],
                    'is_seed' => true,
                ]);
                $createdForDevice++;
                $totalPhotos++;
            }

            if ($createdForDevice > 0) {
                $devicesWithPhotos++;
            }
        }

        $this->photoCounts = [
            'devices_with_photos' => $devicesWithPhotos,
            'total_photos' => $totalPhotos,
            'devices_without_photos' => Mouse::count() + Dongle::count() - $devicesWithPhotos,
            'missing_files' => $missingFiles,
        ];
    }

    private function reportCounts(): void
    {
        $counts = [
            'brands' => MouseProfile::distinct()->count('brand'),
            'mice' => Mouse::count(),
            'dongles' => Dongle::count(),
            'identifications' => Identification::count(),
            'mouse_profiles' => MouseProfile::count(),
            'usb_vendors' => UsbVendor::count(),
            'usb_products' => UsbProduct::count(),
            'device_matches' => DeviceMatch::count(),
        ];

        $this->command->info('Development sample data (is_seed=true), global researched catalogue - NOT real office inventory:');
        $this->command->info('  brands: '.$counts['brands'].' | mouse_profiles: '.$counts['mouse_profiles']);
        $this->command->info('  mice: '.$counts['mice'].' | dongles: '.$counts['dongles'].' | identifications: '.$counts['identifications']);
        $this->command->info('  usb_vendors: '.$counts['usb_vendors'].' | usb_products: '.$counts['usb_products']);
        $this->command->info('  device_matches: '.$counts['device_matches'].' (sample pairs only; no physical test was performed)');
        $this->command->info('  reference photos: '.$this->photoCounts['total_photos'].' across '.$this->photoCounts['devices_with_photos'].' devices'
            .' | no photo found for '.$this->photoCounts['devices_without_photos'].' devices'
            .' | missing files: '.$this->photoCounts['missing_files']);
    }

    private function mouseBySeedId(string $mouseId): Mouse
    {
        return Mouse::where('mouse_id', $mouseId)->firstOrFail();
    }

    private function dongleBySeedId(string $dongleId): Dongle
    {
        return Dongle::where('dongle_id', $dongleId)->firstOrFail();
    }

    /** @return array{vendors: array<string, string>, products: array<int, array{vid: string, pid: string, product_name: string}>} */
    private function usbSnapshot(): array
    {
        if ($this->usbSnapshot === null) {
            /** @var array{vendors: array<string, string>, products: array<int, array{vid: string, pid: string, product_name: string}>} $snapshot */
            $snapshot = require __DIR__.'/usb-ids-snapshot.php';
            $this->usbSnapshot = $snapshot;
        }

        return $this->usbSnapshot;
    }

    /** @return array<string, string> */
    private function usbVendors(): array
    {
        // Curated highlight rows; the complete vendor list comes from the usb.ids snapshot.
        return [
            '046D' => 'Logitech, Inc.',
            '1532' => 'Razer USA, Ltd',
            '045E' => 'Microsoft Corp.',
            '1B1C' => 'Corsair',
            '1038' => 'SteelSeries ApS',
            '0B05' => 'ASUSTek Computer, Inc.',
            '03F0' => 'HP, Inc',
            '413C' => 'Dell Computer Corp.',
            '17EF' => 'Lenovo',
            '054C' => 'Sony Corp.',
            '145F' => 'Trust',
            '0951' => 'Kingston Technology',
            '04D9' => 'Holtek Semiconductor, Inc.',
            '1EA7' => 'SHARKOON Technologies GmbH',
            '05AC' => 'Apple, Inc.',
            '09DA' => 'A4Tech Co., Ltd.',
            '046A' => 'CHERRY',
            '056E' => 'Elecom Co., Ltd',
            '24AE' => 'Shenzhen Rapoo Technology Co., Ltd.',
            '1BCF' => 'Sunplus Innovation Technology Inc.',
            '04F2' => 'Chicony Electronics Co., Ltd',
            '0C45' => 'Microdia',
            '062A' => 'MosArt Semiconductor Corp.',
            '1915' => 'Nordic Semiconductor ASA',
            '0A12' => 'Cambridge Silicon Radio, Ltd',
        ];
    }

    /** @return array<int, array{vid: string, pid: string, product_name: string}> */
    private function usbProducts(): array
    {
        // Curated highlight rows; the full keyword-filtered catalogue comes from the snapshot.
        return [
            ['vid' => '046D', 'pid' => 'C52B', 'product_name' => 'Unifying Receiver'],
            ['vid' => '046D', 'pid' => 'C531', 'product_name' => 'C-U0007 [Unifying Receiver]'],
            ['vid' => '046D', 'pid' => 'C532', 'product_name' => 'Unifying Receiver'],
            ['vid' => '046D', 'pid' => 'C526', 'product_name' => 'Nano Receiver'],
            ['vid' => '046D', 'pid' => 'C52F', 'product_name' => 'Nano Receiver'],
            ['vid' => '046D', 'pid' => 'C534', 'product_name' => 'Nano Receiver'],
            ['vid' => '046D', 'pid' => 'C537', 'product_name' => 'Cordless Mouse Receiver'],
            ['vid' => '046D', 'pid' => 'C539', 'product_name' => 'Lightspeed Receiver'],
            ['vid' => '046D', 'pid' => 'C548', 'product_name' => 'Logi Bolt Receiver'],
            ['vid' => '046D', 'pid' => 'C52E', 'product_name' => 'MK260 Wireless Combo Receiver'],
            ['vid' => '046D', 'pid' => 'C542', 'product_name' => 'M185 compact wireless mouse'],
            ['vid' => '046D', 'pid' => 'B014', 'product_name' => 'Bluetooth Mouse M336/M337/M535'],
            ['vid' => '046D', 'pid' => 'C088', 'product_name' => 'G Pro Wireless gaming mouse (wired mode)'],
            ['vid' => '1532', 'pid' => '007B', 'product_name' => 'RC30-0305 Gaming Mouse Dongle [Viper Ultimate (Wireless)]'],
            ['vid' => '1532', 'pid' => '007A', 'product_name' => 'RC30-0305 Gaming Mouse [Viper Ultimate (Wired)]'],
            ['vid' => '1532', 'pid' => '0088', 'product_name' => 'Gaming Mouse [Basilisk Ultimate, Wireless, Receiver]'],
            ['vid' => '1532', 'pid' => '0083', 'product_name' => 'RC30-0315, Gaming Mouse [Basilisk X HyperSpeed]'],
            ['vid' => '1532', 'pid' => '006F', 'product_name' => 'RZ01-0257 Gaming Mouse [Lancehead Wireless (2019, Wireless, Receiver)]'],
            ['vid' => '045E', 'pid' => '0745', 'product_name' => 'Nano Transceiver v1.0 for Bluetooth'],
            ['vid' => '045E', 'pid' => '07B2', 'product_name' => '2.4GHz Transceiver v8.0 used by mouse Wireless Desktop 900'],
            ['vid' => '045E', 'pid' => '07FD', 'product_name' => 'Nano Transceiver 1.1'],
            ['vid' => '1B1C', 'pid' => '1B65', 'product_name' => 'Harpoon Wireless Dongle'],
            ['vid' => '1B1C', 'pid' => '1B5E', 'product_name' => 'Harpoon Wireless Mouse'],
            ['vid' => '0B05', 'pid' => '17C2', 'product_name' => 'ROG Spitfire'],
            ['vid' => '03F0', 'pid' => '0B0C', 'product_name' => 'Wireless Keyboard and Optical Mouse receiver'],
            ['vid' => '03F0', 'pid' => '0641', 'product_name' => 'X1200 Optical Mouse'],
            ['vid' => '413C', 'pid' => '2501', 'product_name' => 'Keyboard and mouse dongle'],
            ['vid' => '17EF', 'pid' => '6032', 'product_name' => 'Wireless Dongle for Keyboard and Mouse'],
            ['vid' => '17EF', 'pid' => '604B', 'product_name' => 'Precision Wireless Mouse'],
            ['vid' => '145F', 'pid' => '023F', 'product_name' => 'Mouse [GXT 168]'],
            ['vid' => '1EA7', 'pid' => '0030', 'product_name' => 'Trust GXT 158 Orna Laser Gaming Mouse'],
            ['vid' => '04D9', 'pid' => 'FC2A', 'product_name' => 'Gaming Mouse [Redragon M709]'],
            ['vid' => '04D9', 'pid' => 'FC30', 'product_name' => 'Gaming Mouse [Redragon M711]'],
            ['vid' => '04D9', 'pid' => 'FC38', 'product_name' => 'Gaming Mouse [Redragon M602-RGB]'],
            ['vid' => '05AC', 'pid' => '0269', 'product_name' => 'Magic Mouse 2 (Lightning connector)'],
            ['vid' => '09DA', 'pid' => '024F', 'product_name' => 'RF Receiver and G6-20D Wireless Optical Mouse'],
            ['vid' => '046A', 'pid' => '0106', 'product_name' => 'R-300 Wireless Mouse Receiver'],
            ['vid' => '056E', 'pid' => '008F', 'product_name' => 'M-BT8BL Bluetooth Mouse'],
            ['vid' => '24AE', 'pid' => '2001', 'product_name' => '5 GHz Wireless Receiver'],
            ['vid' => '1BCF', 'pid' => '05C5', 'product_name' => 'SPRF2413A [2.4GHz Wireless Keyboard/Mouse Receiver]'],
            ['vid' => '04F2', 'pid' => '0220', 'product_name' => 'Wireless HID Receiver'],
            ['vid' => '062A', 'pid' => '3286', 'product_name' => 'Nano Receiver [Sandstrom Laser Mouse SMWLL11]'],
            ['vid' => '0A12', 'pid' => '0001', 'product_name' => 'Bluetooth Dongle (HCI mode)'],
        ];
    }

    /**
     * Compact builder for a researched wireless-mouse profile.
     *
     * @param  array<string, string|null>  $fields
     * @return array<string, string|null>
     */
    private function profile(string $brand, string $model, ?string $family, ?string $technology, ?string $frequency, array $fields = []): array
    {
        return array_merge([
            'brand' => $brand,
            'model' => $model,
            'vid' => null,
            'pid' => null,
            'receiver_family' => $family,
            'wireless_technology' => $technology,
            'frequency' => $frequency,
            'pairing_utility' => null,
            'official_support_url' => null,
            'notes' => null,
            'source' => null,
        ], $fields);
    }

    /** @return array<int, array<string, string|null>> */
    private function mouseProfileRows(): array
    {
        return [
            // --- Logitech -------------------------------------------------
            $this->profile('Logitech', 'MX Master 3S', 'Logi Bolt / Bluetooth LE', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'Logi Options+', 'official_support_url' => self::LOGI_OPTIONS,
                'notes' => 'No mouse PID in usb.ids. Logi Bolt receiver 046D:C548 is documented separately; the bundled receiver varies by SKU.',
                'source' => 'https://www.logitech.com/en-us/products/mice/mx-master-3s.910-006556.html',
            ]),
            $this->profile('Logitech', 'MX Anywhere 3S', 'Logi Bolt / Bluetooth LE', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'Logi Options+', 'official_support_url' => self::LOGI_OPTIONS,
                'notes' => 'Dual-mode productivity mouse. No model-specific PID in this usb.ids snapshot; receiver bundle varies by SKU.',
                'source' => 'https://www.logitech.com/en-us/products/mice/mx-anywhere-3s.910-006557.html',
            ]),
            $this->profile('Logitech', 'MX Ergo', 'Unifying', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Logi Options+ / Logitech Unifying Software', 'official_support_url' => self::LOGITECH_UNIFYING_ARTICLE,
                'notes' => 'Trackball mouse factory-paired with a Unifying receiver (046D:C52B family); no mouse PID in usb.ids.',
                'source' => self::WIKI_UNIFYING,
            ]),
            $this->profile('Logitech', 'M185 compact wireless mouse', 'Nano', self::TECH_24, self::FREQ_24, [
                'vid' => '046D', 'pid' => 'C542',
                'pairing_utility' => 'Logi Options+ where supported', 'official_support_url' => 'https://www.logitech.com/en-us/products/mice/m185-wireless-mouse.910-001601.html',
                'notes' => 'VID:PID documented verbatim in usb.ids as "M185 compact wireless mouse"; Nano family from documented Nano receivers.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Logitech', 'M235 Wireless Mouse', 'Unifying', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Logitech Unifying Software', 'official_support_url' => self::LOGITECH_UNIFYING_ARTICLE,
                'notes' => 'No model-specific PID in usb.ids; Unifying family and 2.4 GHz band documented. Physical test still required.',
                'source' => self::WIKI_UNIFYING,
            ]),
            $this->profile('Logitech', 'G305 LIGHTSPEED', 'Lightspeed', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Logitech G HUB', 'official_support_url' => self::LOGI_OPTIONS,
                'notes' => 'No mouse PID in usb.ids. Bundled LIGHTSPEED receiver 046D:C539 is a different document entry.',
                'source' => self::LOGI_OPTIONS,
            ]),
            $this->profile('Logitech', 'G Pro Wireless', 'Lightspeed', self::TECH_24, self::FREQ_24, [
                'vid' => '046D', 'pid' => 'C088',
                'pairing_utility' => 'Logitech G HUB', 'official_support_url' => self::LOGI_OPTIONS,
                'notes' => 'usb.ids documents 046D:C088 as the G Pro Wireless gaming mouse in wired mode, not as the receiver.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Logitech', 'Bluetooth Mouse M336/M337/M535', 'Bluetooth', self::TECH_BT, self::FREQ_BT, [
                'vid' => '046D', 'pid' => 'B014',
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => self::LOGI_OPTIONS,
                'notes' => 'VID:PID and product name documented verbatim in usb.ids; Bluetooth operates in the 2.4 GHz ISM band.',
                'source' => self::USB_IDS,
            ]),

            // --- Razer ----------------------------------------------------
            $this->profile('Razer', 'Viper Ultimate', 'Razer HyperSpeed', self::TECH_24, self::FREQ_24, [
                'vid' => '1532', 'pid' => '007B',
                'pairing_utility' => 'Razer Synapse', 'official_support_url' => self::RAZER_SYNAPSE,
                'notes' => '1532:007B is the Viper Ultimate dongle; 1532:007A is the same mouse in wired mode. HyperSpeed is Razer 2.4 GHz.',
                'source' => self::DEVICEHUNT.'1532/device/007B',
            ]),
            $this->profile('Razer', 'Basilisk X HyperSpeed', 'Razer HyperSpeed', self::TECH_DUAL, self::FREQ_24, [
                'vid' => '1532', 'pid' => '0083',
                'pairing_utility' => 'Razer Synapse', 'official_support_url' => self::RAZER_SYNAPSE,
                'notes' => 'usb.ids documents 1532:0083 as the Basilisk X HyperSpeed; the mouse supports HyperSpeed 2.4 GHz and Bluetooth LE.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Razer', 'Basilisk Ultimate', 'Razer HyperSpeed', self::TECH_24, self::FREQ_24, [
                'vid' => '1532', 'pid' => '0088',
                'pairing_utility' => 'Razer Synapse', 'official_support_url' => self::RAZER_SYNAPSE,
                'notes' => '1532:0088 is documented as the Basilisk Ultimate wireless receiver, not the wired mouse.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Razer', 'DeathAdder V2 Pro', 'Razer HyperSpeed', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'Razer Synapse', 'official_support_url' => self::RAZER_SYNAPSE,
                'notes' => 'No wireless PID in this usb.ids snapshot (1532:0084 is the wired DeathAdder V2). HyperSpeed 2.4 GHz + Bluetooth from Razer technology pages.',
                'source' => self::RAZER_HYPERSPEED,
            ]),
            $this->profile('Razer', 'Viper V2 Pro', 'Razer HyperSpeed', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Razer Synapse', 'official_support_url' => self::RAZER_SYNAPSE,
                'notes' => 'No PID in this usb.ids snapshot; left null rather than guessed.',
                'source' => self::RAZER_HYPERSPEED,
            ]),

            // --- Microsoft ------------------------------------------------
            $this->profile('Microsoft', 'Sculpt Ergonomic Mouse', 'Microsoft 2.4 GHz proprietary', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Microsoft Mouse and Keyboard Center', 'official_support_url' => 'https://www.microsoft.com/',
                'notes' => 'No model-specific PID in usb.ids; 2.4 GHz band from Microsoft transceiver entries.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Microsoft', 'Modern Mobile Mouse', 'Bluetooth', self::TECH_BT, self::FREQ_BT, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.microsoft.com/',
                'notes' => 'Bluetooth mouse with no vendor dongle; band from the Bluetooth specification overview.',
                'source' => self::WIKI_BLUETOOTH,
            ]),
            $this->profile('Microsoft', 'Wireless Mobile Mouse 3500', 'Microsoft Nano receiver', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Microsoft Mouse and Keyboard Center', 'official_support_url' => 'https://www.microsoft.com/',
                'notes' => 'No model-specific PID in usb.ids; Nano receiver family inferred from documented Microsoft transceivers.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Microsoft', 'Wireless Desktop 900 mouse', 'Microsoft 2.4 GHz transceiver v8.0', self::TECH_24, self::FREQ_24, [
                'vid' => '045E', 'pid' => '07B2',
                'pairing_utility' => 'Microsoft Mouse and Keyboard Center', 'official_support_url' => 'https://www.microsoft.com/',
                'notes' => '045E:07B2 is the documented receiver PID used by the Wireless Desktop 900 mouse, not the mouse PID.',
                'source' => self::USB_IDS,
            ]),

            // --- Corsair / SteelSeries / ASUS -----------------------------
            $this->profile('Corsair', 'Harpoon Wireless', 'Corsair Slipstream', self::TECH_24, self::FREQ_24, [
                'vid' => '1B1C', 'pid' => '1B5E',
                'pairing_utility' => 'CORSAIR iCUE', 'official_support_url' => self::CORSAIR_ICUE,
                'notes' => '1B1C:1B5E is the Harpoon Wireless mouse in usb.ids; its dongle is 1B1C:1B65. SLIPSTREAM is documented 2.4 GHz.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Corsair', 'Dark Core RGB Pro SE', 'Corsair Slipstream', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'CORSAIR iCUE', 'official_support_url' => self::CORSAIR_ICUE,
                'notes' => 'SLIPSTREAM 2.4 GHz plus Bluetooth; no model-specific PID in this usb.ids snapshot.',
                'source' => self::CORSAIR_SLIPSTREAM,
            ]),
            $this->profile('SteelSeries', 'Rival 650 Wireless', 'SteelSeries 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'SteelSeries GG', 'official_support_url' => self::STEELSERIES_GG,
                'notes' => 'No model-specific receiver PID in usb.ids (1038:1260 is a headset adapter); left null.',
                'source' => self::STEELSERIES_GG,
            ]),
            $this->profile('SteelSeries', 'Rival 3 Wireless', 'SteelSeries 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'SteelSeries GG', 'official_support_url' => self::STEELSERIES_GG,
                'notes' => 'Official product page documents dual 2.4 GHz and Bluetooth 5.0; no PID in usb.ids.',
                'source' => 'https://steelseries.com/gaming-mice',
            ]),
            $this->profile('SteelSeries', 'Aerox 3 Wireless', 'SteelSeries 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'SteelSeries GG', 'official_support_url' => self::STEELSERIES_GG,
                'notes' => 'Official SteelSeries page documents 2.4 GHz wireless; no model-specific PID in this snapshot.',
                'source' => 'https://steelseries.com/gaming-mice/aerox-3-2022',
            ]),
            $this->profile('ASUS', 'ROG Spitfire', 'ASUS ROG 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '0B05', 'pid' => '17C2',
                'pairing_utility' => 'ASUS Armoury Crate', 'official_support_url' => 'https://www.asus.com/support/',
                'notes' => 'VID:PID documented verbatim in usb.ids; 2.4 GHz is the documented ISM band for this receiver class.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('ASUS', 'ROG Gladius II Wireless', 'ASUS ROG 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'ASUS Armoury Crate', 'official_support_url' => 'https://www.asus.com/support/',
                'notes' => 'Official ROG product page documents dual 2.4 GHz / Bluetooth connectivity; no PID in this usb.ids snapshot.',
                'source' => 'https://rog.asus.com/us/mice-mouse-pads/mice/wireless/rog-gladius-ii-wireless-model/',
            ]),
            $this->profile('ASUS', 'ROG Keris Wireless', 'ROG SpeedNova', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'ASUS Armoury Crate', 'official_support_url' => self::ASUS_SPEEDNOVA,
                'notes' => 'Official ROG page documents tri-mode wired / 2.4 GHz RF / Bluetooth LE with SpeedNova; no PID in usb.ids.',
                'source' => 'https://rog.asus.com/us/mice-mouse-pads/mice/wireless/rog-keris-wireless-model',
            ]),

            // --- HP / Dell / Lenovo ---------------------------------------
            $this->profile('HP', 'X1200 Optical Mouse', 'HP 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '03F0', 'pid' => '0641',
                'pairing_utility' => 'HP Support Assistant (no separate utility documented)', 'official_support_url' => 'https://support.hp.com/',
                'notes' => 'VID:PID documented in usb.ids; band from the documented HP wireless receiver entries.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Dell', 'Wireless Mouse WM126', 'Dell Universal Pairing 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Dell Universal Pairing utility', 'official_support_url' => 'https://www.dell.com/support/',
                'notes' => 'Dell user guide documents 2.4 GHz bidirectional wireless and up to six devices on a Universal Receiver; no mouse PID in usb.ids.',
                'source' => 'https://dl.dell.com/manuals/all-products/esuprt_electronics/esuprt_mice_keyboards/dell-wireless-mouse-wm126_user%27s%20guide_en-us.pdf',
            ]),
            $this->profile('Dell', 'Multi-Device Wireless Mouse MS5320W', 'Dell 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'Dell Peripheral Manager', 'official_support_url' => 'https://www.dell.com/support/',
                'notes' => 'Dell client-peripherals catalogue documents two Bluetooth 5.0 channels and one 2.4 GHz RF channel; no PID in usb.ids.',
                'source' => 'https://www.dell.com/support/',
            ]),
            $this->profile('Lenovo', 'Precision Wireless Mouse', 'Lenovo 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '17EF', 'pid' => '604B',
                'pairing_utility' => 'Lenovo Settings / ThinkPad settings', 'official_support_url' => 'https://pcsupport.lenovo.com/',
                'notes' => 'VID:PID and product name documented in usb.ids; receiver 17EF:6032 is a separate entry.',
                'source' => self::USB_IDS,
            ]),

            // --- Sony / Apple ---------------------------------------------
            $this->profile('Sony', 'VGP-BMS10 Bluetooth Laser Mouse', 'Bluetooth', self::TECH_BT, self::FREQ_BT, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.sony.com/electronics/support',
                'notes' => 'Sony support specifications document a 2.4 GHz frequency band and Bluetooth communication; no USB PID.',
                'source' => 'https://sony.co.nz/electronics/support/accessories-personal-computer-accessories/vgp-bms10/specifications',
            ]),
            $this->profile('Sony', 'INZONE Mouse-A (MSE-G500B)', 'Sony 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Sony INZONE Hub', 'official_support_url' => 'https://electronics.sony.com/audio/gaming-audio/inzone-peripherals/p/mseg500b',
                'notes' => 'Official Sony product page documents a 2.4 GHz connection; no USB PID in this snapshot.',
                'source' => 'https://electronics.sony.com/audio/gaming-audio/inzone-peripherals/p/mseg500b',
            ]),
            $this->profile('Apple', 'Magic Mouse 2', 'Bluetooth', self::TECH_BT, self::FREQ_BT, [
                'vid' => '05AC', 'pid' => '0269',
                'pairing_utility' => 'macOS Bluetooth settings', 'official_support_url' => 'https://support.apple.com/',
                'notes' => 'usb.ids documents 05AC:0269 for the Lightning-charging Magic Mouse 2; USB is for charging, data is Bluetooth.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Apple', 'Magic Mouse (USB-C)', 'Bluetooth', self::TECH_BT, self::FREQ_BT, [
                'pairing_utility' => 'macOS Bluetooth settings', 'official_support_url' => 'https://support.apple.com/',
                'notes' => 'Current USB-C Magic Mouse; pairs over Bluetooth with no vendor dongle. No PID in this usb.ids snapshot.',
                'source' => 'https://support.apple.com/',
            ]),

            // --- Trust / A4Tech / Rapoo / Keychron ------------------------
            $this->profile('Trust', 'GXT 168 Mouse', 'Trust 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '145F', 'pid' => '023F',
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.trust.com/en/support',
                'notes' => 'VID:PID and product name documented verbatim in usb.ids.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Trust', 'GXT 158 Orna Laser Gaming Mouse', 'Trust 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '1EA7', 'pid' => '0030',
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.trust.com/en/support',
                'notes' => 'usb.ids product string names the Trust GXT 158 Orna but files it under VID 1EA7, labelled SHARKOON in this snapshot. Both facts are recorded, not guessed.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('A4Tech', 'G6-20D Wireless Optical Mouse', 'A4Tech 2.4 GHz (RXM-15 style)', self::TECH_24, self::FREQ_24, [
                'vid' => '09DA', 'pid' => '024F',
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.a4tech.com/support/',
                'notes' => 'usb.ids documents 09DA:024F as "RF Receiver and G6-20D Wireless Optical Mouse" (combined entry).',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Rapoo', 'M500 Wireless Mouse', 'Rapoo 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.rapoo.com/',
                'notes' => 'Official Rapoo product pages document the M500 wireless mouse; no model-specific PID in this snapshot (24AE:2001 is a 5 GHz receiver).',
                'source' => 'https://rapoo.in/m500/',
            ]),
            $this->profile('Rapoo', 'M100 Silent Wireless Mouse', 'Rapoo 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'Rapoo Office Device Driver', 'official_support_url' => 'https://www.rapoo.com/en/support/downloadcenter',
                'notes' => 'Official Rapoo page documents 2.4 GHz dongle plus Bluetooth; no model-specific PID in usb.ids.',
                'source' => 'https://rapoo-eu.com/product/m100-silent',
            ]),
            $this->profile('Keychron', 'M3 Wireless Mouse', 'Bluetooth / 2.4 GHz', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'Keychron Web Driver / operating-system Bluetooth settings', 'official_support_url' => 'https://www.keychron.com/pages/support',
                'notes' => 'No Keychron mouse VID/PID in this usb.ids snapshot; left null.',
                'source' => 'https://www.keychron.com/pages/support',
            ]),

            // --- HyperX / Redragon / Glorious ------------------------------
            $this->profile('HyperX', 'Pulsefire Dart', 'HyperX 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'HyperX NGENUITY', 'official_support_url' => 'https://www.hyperx.com/pages/support',
                'notes' => 'No wireless-mouse PID in this usb.ids snapshot (0951:16C4 is a Cloud Flight headset); left null.',
                'source' => 'https://www.hyperx.com/pages/support',
            ]),
            $this->profile('HyperX', 'Pulsefire Haste 2 Wireless', 'HyperX 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'HyperX NGENUITY', 'official_support_url' => 'https://www.hyperx.com/pages/support',
                'notes' => 'Wireless HyperSpeed-family gaming mouse; no PID in this usb.ids snapshot.',
                'source' => 'https://www.hyperx.com/pages/support',
            ]),
            $this->profile('Redragon', 'M711 Cobra', 'Redragon 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '04D9', 'pid' => 'FC30',
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.redragonzone.com/',
                'notes' => 'usb.ids documents 04D9:FC30 under the Holtek OEM VID; verify whether the physical unit is the wired or wireless M711 variant.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Redragon', 'M709', 'Redragon 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '04D9', 'pid' => 'FC2A',
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.redragonzone.com/',
                'notes' => 'usb.ids documents 04D9:FC2A as "Gaming Mouse [Redragon M709]" under the Holtek OEM VID.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Redragon', 'M602-RGB', 'Redragon 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '04D9', 'pid' => 'FC38',
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.redragonzone.com/',
                'notes' => 'usb.ids documents 04D9:FC38 as "Gaming Mouse [Redragon M602-RGB]".',
                'source' => self::USB_IDS,
            ]),
            $this->profile('Glorious', 'Model O Wireless', 'Glorious 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Glorious Core', 'official_support_url' => 'https://www.gloriousgaming.com/pages/support',
                'notes' => 'Official product guide lists a 2.4 GHz USB receiver; no VID/PID in this usb.ids snapshot.',
                'source' => 'https://www.gloriousgaming.com/pages/guide-model-o-wireless',
            ]),
            $this->profile('Glorious', 'Model D Wireless', 'Glorious 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'Glorious Core', 'official_support_url' => 'https://www.gloriousgaming.com/pages/support',
                'notes' => 'Official product guide lists a 2.4 GHz USB receiver; no VID/PID in this usb.ids snapshot.',
                'source' => 'https://gloriousgaming.com/pages/guide-model-d-wireless',
            ]),

            // --- MSI / Gigabyte / Samsung / LG ----------------------------
            $this->profile('MSI', 'Clutch GM41 Lightweight Wireless', 'MSI 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'MSI Center', 'official_support_url' => 'https://www.msi.com/Gaming-Gear/CLUTCH-GM41-LIGHTWEIGHT-WIRELESS/support',
                'notes' => 'Official MSI page documents 2.4G RF with 1 ms latency and a USB wireless dongle; no PID in usb.ids.',
                'source' => 'https://www.msi.com/Gaming-Gear/CLUTCH-GM41-LIGHTWEIGHT-WIRELESS',
            ]),
            $this->profile('Gigabyte', 'AORUS M6', 'AORUS 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'GIGABYTE CONTROL CENTER', 'official_support_url' => 'https://gigabyte.com/Mouse/AORUS-M6/sp',
                'notes' => 'Official GIGABYTE specifications list USB Type-C / 2.4 GHz Wireless; no PID in this usb.ids snapshot.',
                'source' => 'https://gigabyte.com/Mouse/AORUS-M6/sp',
            ]),
            $this->profile('Samsung', 'Wireless Mouse AA-SM7PWR', 'Samsung 2.4 GHz RF', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.samsung.com/',
                'notes' => 'Samsung accessory specifications document a 2.4G RF interface; no model-specific PID in usb.ids.',
                'source' => 'http://samsung.com/uk/consumer/pc-peripherals/pc-accessories/pc-accessories/AA-SM7PWRS/E',
            ]),
            $this->profile('LG', 'gram Wireless Mouse MSA2', 'LG 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.lg.com/us/computer-accessories/lg-gram-msa2-wireless-mouse',
                'notes' => 'Official LG page documents plug-and-play 2.4 GHz wireless with a USB receiver; no PID in usb.ids.',
                'source' => 'https://www.lg.com/us/computer-accessories/lg-gram-msa2-wireless-mouse',
            ]),
            $this->profile('LG', 'MEB-300 Dual Mode Wireless Mouse', 'LG Bluetooth / 2.4 GHz', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.lg.com/',
                'notes' => 'LG product page documents Bluetooth 4.1 plus 2.4 GHz band RF; no PID in this usb.ids snapshot.',
                'source' => 'https://www.lg.com/jp/lg-mobile-phones/meb-300',
            ]),

            // --- Xiaomi / Huawei / Anker / Baseus / Ugreen -----------------
            $this->profile('Xiaomi', 'Xiaomi Wireless Mouse 3', 'Xiaomi 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://mi.com/global/support/faq/details/KA-280089',
                'notes' => 'Official Xiaomi FAQ documents one 2.4G channel and two Bluetooth channels; no PID in usb.ids.',
                'source' => 'https://mi.com/global/product/xiaomi-wireless-mouse-3',
            ]),
            $this->profile('Xiaomi', 'Xiaomi Dual-mode Wireless Mouse 2', 'Xiaomi 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://mi.com/global/support/faq/details/KA-497422',
                'notes' => 'Official Xiaomi FAQ documents 2.4 GHz/Bluetooth dual mode with a toggle switch; no PID in usb.ids.',
                'source' => 'https://mi.com/global/product/xiaomi-dual-mode-wireless-mouse-2',
            ]),
            $this->profile('Xiaomi', 'Xiaomi Wireless Mouse Lite', 'Xiaomi 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://mi.com/global/product/xiaomi-wireless-mouse-lite',
                'notes' => 'Official Xiaomi page documents a mini 2.4 GHz Nano receiver stored in the mouse; no PID in usb.ids.',
                'source' => 'https://mi.com/global/product/xiaomi-wireless-mouse-lite',
            ]),
            $this->profile('Huawei', 'HUAWEI Bluetooth Mouse (2nd generation) CD23', 'Bluetooth', self::TECH_BT, self::FREQ_BT, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://consumer.huawei.com/en/support/accessories/bluetooth-mouse-2nd-generation',
                'notes' => 'Official Huawei support lists CD23 / CD23-U / CD23 SE models; Bluetooth band from the Bluetooth specification.',
                'source' => 'https://consumer.huawei.com/en/support/accessories/bluetooth-mouse-2nd-generation',
            ]),
            $this->profile('Huawei', 'HUAWEI Wireless Mouse (Pro Edition) WYN', 'Huawei 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://consumer.huawei.com/en/support/',
                'notes' => 'Official Huawei mouse support content lists the WYN Pro Edition model; no PID in this usb.ids snapshot.',
                'source' => 'https://consumer.huawei.com/en/support/content/en-us15907769',
            ]),
            $this->profile('Anker', 'Anker 2.4G Wireless Vertical Ergonomic Mouse A7852', 'Anker 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://support.anker.com/article-description/Anker-Mouse-FAQ',
                'notes' => 'Official Anker product page documents a 2.4G connection and a model-specific A7890 receiver; no PID in usb.ids.',
                'source' => 'https://anker.com/products/a7582',
            ]),
            $this->profile('Baseus', 'Baseus F01B Tri-Mode Wireless Mouse', 'Baseus 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.baseus.com/',
                'notes' => 'Official/listed product page documents Bluetooth 5.0 and 2.4 GHz; no PID in this usb.ids snapshot.',
                'source' => 'https://baseusofficialstore.com/product/baseus-f01b-tri-mode-wireless-mouse/',
            ]),
            $this->profile('Baseus', 'Baseus F02 Ergonomic Wireless Mouse', 'Baseus 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.baseus.com/',
                'notes' => 'Product specifications list model BS-F02 with 2.4 GHz and Bluetooth 5.0/5.2; no PID in usb.ids.',
                'source' => 'https://baseus.co.za/products/baseus-f02-ergonomic-wireless-mouse-without-battery',
            ]),
            $this->profile('Ugreen', 'UGREEN Dual-Mode Wireless Mouse 90531 (MU001)', 'UGREEN 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.ugreen.com/',
                'notes' => 'UGREEN product specifications give model 90531 / MU001 with Bluetooth + 2.4 GHz; no PID in usb.ids.',
                'source' => 'https://www.ugreen.com/en-au/products/au-90531',
            ]),

            // --- CHERRY / Fujitsu / Philips --------------------------------
            $this->profile('CHERRY', 'R-300 Wireless Mouse', 'CHERRY 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'vid' => '046A', 'pid' => '0106',
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.cherry.de/',
                'notes' => '046A:0106 is documented in usb.ids as the R-300 Wireless Mouse Receiver, not the mouse PID.',
                'source' => self::USB_IDS,
            ]),
            $this->profile('CHERRY', 'CHERRY XTRFY M42 Wireless', 'CHERRY XTRFY 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://cherry.de/en-us/product/m42-rgb-wireless',
                'notes' => 'Official CHERRY page documents 2.4 GHz lag-free wireless; no PID in this usb.ids snapshot.',
                'source' => 'https://cherry.de/en-us/product/m42-rgb-wireless',
            ]),
            $this->profile('Fujitsu', 'Wireless Notebook Mouse WI610', 'Fujitsu 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://fujitsu.com/global/products/computing/peripheral/accessories/input-devices/mice',
                'notes' => 'Official Fujitsu page documents 2.4 GHz wireless; no model-specific PID in usb.ids.',
                'source' => 'https://fujitsu.com/uk/products/computing/peripheral/accessories/input-devices/mice/wireless-notebook-mouse-wi610.html',
            ]),
            $this->profile('Fujitsu', 'Wireless Mouse WI200', 'Fujitsu 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://fujitsu.com/global/products/computing/peripheral/accessories/input-devices/mice',
                'notes' => 'Official Fujitsu page documents the latest 2.4 GHz technology and a 1000 dpi sensor; no PID in usb.ids.',
                'source' => 'https://fujitsu.com/global/products/computing/peripheral/accessories/input-devices/mice/wireless-mouse-wi200.html',
            ]),
            $this->profile('Philips', 'Wireless mouse SPK7384/00', 'Philips 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.philips.com/',
                'notes' => 'Official Philips product sheet states 2.4GHz Wireless and an optical sensor; no PID in usb.ids.',
                'source' => 'https://documents.philips.com/assets/20230601/a8b90a2ce6234c83ad10b01400ed4ce1.pdf',
            ]),
            $this->profile('Philips', 'Multi-device Bluetooth mouse SPK7354/93', 'Bluetooth / 2.4 GHz', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.philips.com/',
                'notes' => 'Official Philips product sheet documents Bluetooth 4.0 and 2.4 GHz dual connection; no PID in usb.ids.',
                'source' => 'https://documents.philips.com/assets/20230612/b6d9e064351441d7bd42b01f01277fea.pdf',
            ]),

            // --- Belkin / Targus / Kensington ------------------------------
            $this->profile('Belkin', 'Wireless Keyboard and Mouse Combo BBZ010tt', 'Belkin 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.belkin.com/support-article',
                'notes' => 'Official Belkin page documents a 2.4 GHz wireless connection and included USB-A receiver; no PID in usb.ids.',
                'source' => 'https://belkin.com/p/wireless-keyboard-and-mouse-combo/BBZ010tt.html',
            ]),
            $this->profile('Targus', 'Wireless Blue Trace Mouse AMW50US', 'Targus 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://us.targus.com/collections/mice',
                'notes' => 'Official Targus page documents 2.4 GHz technology with a Stow-N-Go USB receiver; no PID in usb.ids.',
                'source' => 'https://us.targus.com/collections/all/products/wireless-blue-trace-mouse-amw50us',
            ]),
            $this->profile('Targus', 'W600 Hi-Def Wireless Optical Mouse AMW60001GL', 'Targus 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://us.targus.com/collections/mice',
                'notes' => 'Official Targus page documents 2.4 GHz wireless and a 1600 dpi sensor; no PID in usb.ids.',
                'source' => 'https://us.targus.com/collections/all/products/w600-hi-def-wireless-optical-mouse-amw60001gl',
            ]),
            $this->profile('Kensington', 'Pro Fit Ergo Wireless Mouse', 'Kensington 2.4 GHz / Bluetooth LE', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.kensington.com/',
                'notes' => 'Official Kensington page documents dual 2.4 GHz and Bluetooth 4.0 LE with AES encryption; no PID in usb.ids.',
                'source' => 'https://kensington.com/p/products/ergonomic-desk-accessories/ergonomic-input-devices/pro-fit-ergo-wireless-mouse',
            ]),

            // --- E-Blue / Fantech / Rexus / Tecware / Inphic --------------
            $this->profile('E-Blue', 'Mazer II Type-R EMS601 Wireless Gaming Mouse', 'E-Blue 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://www.eblue.com.tw/',
                'notes' => 'Reseller product listing documents model EMS601 with 2.4 GHz wireless; no PID in this usb.ids snapshot.',
                'source' => 'https://scan.co.uk/products/e-blue-ems601gy-mazer-ii-type-r-24ghz-wireless-gaming-mouse-optical-6-buttons-500-1000-1750-2500-dpi',
            ]),
            $this->profile('Fantech', 'Aria XD7', 'Fantech 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'Fantech software', 'official_support_url' => 'https://fantechworld.com/',
                'notes' => 'Official Fantech product page documents tri-mode BT5.0 / wired / 2.4 GHz; no PID in usb.ids.',
                'source' => 'https://fantechworld.com/products/aria-xd7-huano-version',
            ]),
            $this->profile('Rexus', 'Arka II RX-107 Wireless Gaming Mouse', 'Rexus 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://rexus.id/',
                'notes' => 'Official Rexus page documents a dual-connection wireless gaming mouse with a PAW3311 sensor; no PID in usb.ids.',
                'source' => 'https://rexus.id/products/rexus-mouse-wireless-gaming-arka-ii-rx-107-dual-connection',
            ]),
            $this->profile('Rexus', 'RIVA RX-120 Wireless Gaming Mouse', 'Rexus 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://rexus.id/',
                'notes' => 'Official Rexus page documents triple connection (wired / 2.4 GHz / Bluetooth); no PID in usb.ids.',
                'source' => 'https://rexus.id/products/rexus-mouse-gaming-riva-rx-120-lightweight-triple-connection-paw3311',
            ]),
            $this->profile('Tecware', 'EX2 Professional Gaming Mouse', 'Tecware 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.tecware.co/',
                'notes' => 'Official Tecware page documents 2.4 GHz + Bluetooth wireless and wired connection; no PID in usb.ids.',
                'source' => 'https://www.tecware.co/ex2-professional-gaming-mouse',
            ]),
            $this->profile('Inphic', 'Inphic PM6 Pro Wireless Mouse', 'Inphic 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.inphic.com/',
                'notes' => 'Official Inphic catalogue documents 2.4 GHz and Bluetooth 4.0/5.0; no PID in usb.ids.',
                'source' => 'https://www.inphic.com/collections/wireless-mouse',
            ]),
            $this->profile('Inphic', 'Inphic X2 V2 Wireless Mouse', 'Inphic 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.inphic.com/',
                'notes' => 'Official Inphic page documents 2.4 GHz, Bluetooth 5.0 and Bluetooth 4.0; no PID in usb.ids.',
                'source' => 'https://www.inphic.com/collections/wireless-mouse',
            ]),

            // --- Xtrfy / Akko / Bloody / Sunsonny --------------------------
            $this->profile('Xtrfy', 'M42 Wireless', 'Xtrfy 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://cherryxtrfy.com/',
                'notes' => 'Official product page documents 2.4 GHz wireless / USB and a bundled dongle; Xtrfy is now CHERRY XTRFY. No PID in usb.ids.',
                'source' => 'https://cherryxtrfy.com/mice/m42-wireless',
            ]),
            $this->profile('Akko', 'AG325W Wireless Gaming Mouse', 'Akko 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://en.akkogear.com/',
                'notes' => 'Official Akko page documents Bluetooth + 2.4G dual mode; no PID in usb.ids.',
                'source' => 'https://en.akkogear.com/product/ag325w',
            ]),
            $this->profile('Akko', 'Akko Dash Wireless Mouse', 'Akko 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://en.akkogear.com/',
                'notes' => 'Official Akko page documents 2.4GHz / Bluetooth / wired tri-mode; no PID in usb.ids.',
                'source' => 'https://en.akkogear.com/product/dash-hello-kitty-mouse',
            ]),
            $this->profile('A4Tech Bloody', 'Bloody R72 Ultra', 'Bloody 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => 'A4Tech Bloody software', 'official_support_url' => 'https://www.a4tech.com/',
                'notes' => 'Product listings document dual-mode 2.4 GHz / USB-C with a 2.4G gaming receiver; no PID in usb.ids.',
                'source' => 'https://www.a4tech.com/',
            ]),
            $this->profile('A4Tech Bloody', 'Bloody W95 Max', 'Bloody 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => 'A4Tech Bloody software', 'official_support_url' => 'https://www.a4tech.com/',
                'notes' => 'Product listing documents 2.4GHz / Bluetooth / wired tri-mode connectivity; no PID in usb.ids.',
                'source' => 'https://www.a4tech.com/',
            ]),
            $this->profile('Sunsonny', 'Sunsonny SR8 Wireless Optical Mouse', 'Sunsonny 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://sunsonny.com/Down.html',
                'notes' => 'Official Sunsonny driver page lists the SR8 wireless instant-action optical mouse; no PID in usb.ids.',
                'source' => 'https://sunsonny.com/Down.html',
            ]),
            $this->profile('Sunsonny', 'Sunsonny SR-8509III 2.4GHz Wireless Gaming Mouse', 'Sunsonny 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://sunsonny.com/',
                'notes' => 'Product listing documents a 2.4 GHz wireless gaming mouse with a Nano receiver; no PID in usb.ids.',
                'source' => 'https://www.amazon.ca/ZCL-Sunsonny-SR-8509III-Wireless-Receiver/dp/B00P0TWGA8',
            ]),
            $this->profile('Fujitsu-Siemens', 'Fujitsu Siemens Mouse Esprimo Q', null, null, null, [
                'pairing_utility' => null, 'official_support_url' => 'https://www.fujitsu.com/',
                'notes' => 'usb.ids documents 04D9:2206 but does not state the radio family or band; both left null deliberately.',
                'source' => self::USB_IDS,
            ]),

            // --- Genius / Perixx / Verbatim / Hama -------------------------
            $this->profile('Genius', 'Genius Ergo 9000S Pro', 'Genius 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://geniusnet.com/',
                'notes' => 'Official Genius page documents dual Bluetooth 5.0 and 2.4 GHz connectivity; no PID in usb.ids.',
                'source' => 'https://geniusnet.com/en/products/view/ergo_9000s_pro',
            ]),
            $this->profile('Perixx', 'PERIMICE-803A Wireless Multi-Device Mouse', 'Perixx 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://perixx.com/',
                'notes' => 'Official Perixx page documents 2.4 GHz, Bluetooth 5.0 and Bluetooth 3.0 with an internal receiver; no PID in usb.ids.',
                'source' => 'https://perixx.com/products/perimice-803a-wireless-ergonomic-multi-device-mouse',
            ]),
            $this->profile('Verbatim', 'Go Nano Wireless Mouse', 'Verbatim 2.4 GHz', self::TECH_24, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_NONE, 'official_support_url' => 'https://verbatim.com/en/',
                'notes' => 'Official Verbatim page documents 2.4 GHz RF with a plug-in Nano receiver; no PID in usb.ids.',
                'source' => 'https://verbatim.com.au/products/go-nano-wireless-mouse',
            ]),
            $this->profile('Verbatim', 'Wireless Vertical Mouse 67080', 'Verbatim 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://verbatim.com/en/',
                'notes' => 'Official Verbatim page documents Bluetooth 5.4 and 2.4 GHz for up to three devices; no PID in usb.ids.',
                'source' => 'https://verbatim.com.au/products/wireless-vertical-mouse-2-4g-bluetooth-67080',
            ]),
            $this->profile('Hama', 'WM-450 Multi-Device Mouse', 'Hama 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.hama.com/',
                'notes' => 'Official Hama page documents one 2.4 GHz radio channel and two Bluetooth channels; no PID in usb.ids.',
                'source' => 'https://ae.hama.com/00173040/hama-wm-450-multi-device-mouse-wireless-radio-bluetooth-r-7-buttons-blue',
            ]),
            $this->profile('Hama', 'WM-800 Multi-Device Mouse', 'Hama 2.4 GHz / Bluetooth', self::TECH_DUAL, self::FREQ_24, [
                'pairing_utility' => self::UTILITY_OS_BT, 'official_support_url' => 'https://www.hama.com/',
                'notes' => 'Official Hama page documents Bluetooth and 2.4 GHz radio with a 10 m range; no PID in usb.ids.',
                'source' => 'https://ae.hama.com/00173096/hama-wm-800-multi-device-mouse-ergonomic-wireless-bluetooth-r-battery-black',
            ]),
        ];
    }

    /** @return array<int, array<string, string>> */
    private function mouseRows(): array
    {
        // [registration id, brand, model, colour, connectivity, physical description, notes]
        $rows = [
            ['MS-SEED001', 'Logitech', 'MX Master 3S', 'Graphite', '2.4GHz wireless / Bluetooth',
                'Full-size contoured optical mouse with a side wheel and a textured soft-touch shell.',
                'Sample record. Official Logitech product page; receiver bundle varies by SKU.'],
            ['MS-SEED002', 'Logitech', 'M185 compact wireless mouse', 'Grey', '2.4GHz wireless',
                'Compact ambidextrous optical mouse with a small Nano receiver stored in the battery compartment.',
                'Sample record. VID:PID 046D:C542 documented in usb.ids.'],
            ['MS-SEED003', 'Logitech', 'MX Ergo', 'Black', '2.4GHz wireless',
                'Trackball mouse with an adjustable angled base and a recessed red trackball.',
                'Sample record. Factory-paired Unifying receiver; verify the individual receiver physically.'],
            ['MS-SEED004', 'Logitech', 'G305 LIGHTSPEED', 'Black', '2.4GHz wireless',
                'Compact symmetrical gaming mouse with a storage compartment for its LIGHTSPEED receiver.',
                'Sample record. Receiver family from documented LIGHTSPEED receiver entries.'],
            ['MS-SEED005', 'Logitech', 'Bluetooth Mouse M336/M337/M535', 'Black', 'Bluetooth',
                'Slim portable mouse with a quiet scroll wheel and no dedicated USB receiver.',
                'Sample record. 046D:B014 documented in usb.ids; Bluetooth devices pair to the host.'],
            ['MS-SEED006', 'Razer', 'Viper Ultimate', 'Black', '2.4GHz wireless',
                'Lightweight ambidextrous gaming mouse with side buttons and a matching charging dock.',
                'Sample record. HyperSpeed dongle 1532:007B documented in usb.ids/DeviceHunt.'],
            ['MS-SEED007', 'Razer', 'Basilisk X HyperSpeed', 'Black', '2.4GHz wireless / Bluetooth',
                'Ergonomic gaming mouse with textured side grips and a mode switch underneath.',
                'Sample record. VID:PID 1532:0083 documented in usb.ids.'],
            ['MS-SEED008', 'Razer', 'DeathAdder V2 Pro', 'Black', '2.4GHz wireless / Bluetooth',
                'Sculpted right-handed gaming mouse with RGB lighting and a matte finish.',
                'Sample record. No wireless PID in this usb.ids snapshot; left unknown.'],
            ['MS-SEED009', 'Microsoft', 'Sculpt Ergonomic Mouse', 'Black', '2.4GHz wireless',
                'Curved ergonomic mouse with a separate thumb scoop and a four-way scroll wheel.',
                'Sample record. Pairs through Microsoft Mouse and Keyboard Center.'],
            ['MS-SEED010', 'Microsoft', 'Modern Mobile Mouse', 'Mint Green', 'Bluetooth',
                'Slim portable mouse with a foldable design and no USB receiver of its own.',
                'Sample record. Bluetooth devices do not use a vendor dongle.'],
            ['MS-SEED011', 'Microsoft', 'Wireless Desktop 900 mouse', 'Black', '2.4GHz wireless',
                'Low-profile office mouse matching a combined keyboard-and-mouse transceiver set.',
                'Sample record. 045E:07B2 documents the receiver, not the mouse PID.'],
            ['MS-SEED012', 'Corsair', 'Harpoon Wireless', 'Black', '2.4GHz wireless',
                'Lightweight gaming mouse with a contoured shell and Corsair logo.',
                'Sample record. Matching dongle 1B1C:1B65 documented in usb.ids.'],
            ['MS-SEED013', 'SteelSeries', 'Rival 650 Wireless', 'Black', '2.4GHz wireless',
                'Ergonomic dual-sensor gaming mouse with a weight-tuning system.',
                'Sample record. No receiver PID documented in usb.ids; left unknown.'],
            ['MS-SEED014', 'SteelSeries', 'Aerox 3 Wireless', 'Black', '2.4GHz wireless',
                'Honeycomb-shell ultra-light gaming mouse with a compact receiver.',
                'Sample record. SteelSeries product page documents 2.4 GHz wireless.'],
            ['MS-SEED015', 'ASUS', 'ROG Spitfire', 'Black', '2.4GHz wireless',
                'Gaming mouse with an ROG emblem, RGB lighting and a matching wireless receiver.',
                'Sample record. VID:PID 0B05:17C2 documented in usb.ids.'],
            ['MS-SEED016', 'ASUS', 'ROG Keris Wireless', 'Black', '2.4GHz wireless / Bluetooth',
                'Lightweight FPS mouse with swappable switches and a tri-mode connectivity selector.',
                'Sample record. SpeedNova 2.4 GHz documented by ASUS; no PID in usb.ids.'],
            ['MS-SEED017', 'HP', 'X1200 Optical Mouse', 'Black', '2.4GHz wireless',
                'Small black optical mouse with a compact plug-and-play receiver.',
                'Sample record. VID:PID 03F0:0641 documented in usb.ids.'],
            ['MS-SEED018', 'Dell', 'Wireless Mouse WM126', 'Black', '2.4GHz wireless',
                'Ambidextrous matte-black mouse with a small Universal Pairing receiver.',
                'Sample record. Dell user guide documents 2.4 GHz bidirectional wireless.'],
            ['MS-SEED019', 'Dell', 'Multi-Device Wireless Mouse MS5320W', 'Black', '2.4GHz wireless / Bluetooth',
                'Slim multi-device mouse with a mode button for three paired hosts.',
                'Sample record. Dell catalogue documents 2.4 GHz plus two Bluetooth channels.'],
            ['MS-SEED020', 'Lenovo', 'Precision Wireless Mouse', 'Black', '2.4GHz wireless',
                'Slim black wireless mouse with a scroll wheel and Lenovo logo.',
                'Sample record. VID:PID 17EF:604B documented in usb.ids.'],
            ['MS-SEED021', 'Sony', 'VGP-BMS10 Bluetooth Laser Mouse', 'Black', 'Bluetooth',
                'Slim VAIO-branded laser mouse that pairs directly to a Bluetooth host.',
                'Sample record. Sony specifications document a 2.4 GHz Bluetooth radio.'],
            ['MS-SEED022', 'Sony', 'INZONE Mouse-A (MSE-G500B)', 'Black', '2.4GHz wireless',
                'Ultra-light gaming mouse with a perforated-looking matte shell and INZONE badge.',
                'Sample record. Sony product page documents a 2.4 GHz connection.'],
            ['MS-SEED023', 'Apple', 'Magic Mouse 2', 'White', 'Bluetooth',
                'Low-profile white mouse with a seamless multi-touch top surface and Lightning charging port.',
                'Sample record. VID:PID 05AC:0269 documented in usb.ids.'],
            ['MS-SEED024', 'Apple', 'Magic Mouse (USB-C)', 'White', 'Bluetooth',
                'Multi-touch glass-top mouse with a recessed USB-C charging port.',
                'Sample record. Pairs over Bluetooth; no vendor dongle.'],
            ['MS-SEED025', 'Trust', 'GXT 168 Mouse', 'Black', '2.4GHz wireless',
                'Black gaming mouse with an illuminated scroll wheel and a compact receiver.',
                'Sample record. VID:PID 145F:023F documented in usb.ids.'],
            ['MS-SEED026', 'Trust', 'GXT 158 Orna Laser Gaming Mouse', 'Black', '2.4GHz wireless',
                'Full-size laser gaming mouse with side buttons and a slim wireless receiver.',
                'Sample record. usb.ids files 1EA7:0030 under SHARKOON but names this Trust model.'],
            ['MS-SEED027', 'A4Tech', 'G6-20D Wireless Optical Mouse', 'Black', '2.4GHz wireless',
                'Black optical mouse supplied with a matched RXM-15 style RF receiver.',
                'Sample record. 09DA:024F documented in usb.ids as a combined receiver/mouse entry.'],
            ['MS-SEED028', 'Rapoo', 'M500 Wireless Mouse', 'Blue', '2.4GHz wireless',
                'Curved blue wireless mouse with a nano-sized receiver and silent clicks.',
                'Sample record. No model-specific PID in this usb.ids snapshot.'],
            ['MS-SEED029', 'Rapoo', 'M100 Silent Wireless Mouse', 'Grey', '2.4GHz wireless / Bluetooth',
                'Small grey mouse with a mode switch for a 2.4 GHz dongle or Bluetooth.',
                'Sample record. Official Rapoo page documents dual-mode connectivity.'],
            ['MS-SEED030', 'Keychron', 'M3 Wireless Mouse', 'Black', '2.4GHz wireless / Bluetooth',
                'Compact wireless mouse designed to pair with Keychron keyboards.',
                'Sample record. No VID/PID in this usb.ids snapshot; left unknown.'],
            ['MS-SEED031', 'HyperX', 'Pulsefire Dart', 'Black', '2.4GHz wireless',
                'Symmetrical gaming mouse with RGB lighting and a wireless-charging-compatible base.',
                'Sample record. No wireless-mouse PID documented in usb.ids.'],
            ['MS-SEED032', 'HyperX', 'Pulsefire Haste 2 Wireless', 'Black', '2.4GHz wireless',
                'Honeycomb-shell lightweight gaming mouse with a small USB receiver.',
                'Sample record. No PID in this usb.ids snapshot.'],
            ['MS-SEED033', 'Redragon', 'M711 Cobra', 'Black', '2.4GHz wireless',
                'Gaming mouse with seven programmable buttons and RGB lighting.',
                'Sample record. 04D9:FC30 documented under the Holtek OEM VID; variant must be checked.'],
            ['MS-SEED034', 'Redragon', 'M709', 'Black', '2.4GHz wireless',
                'Ergonomic gaming mouse with a contoured grip and a compact receiver.',
                'Sample record. 04D9:FC2A documented in usb.ids.'],
            ['MS-SEED035', 'Glorious', 'Model O Wireless', 'White', '2.4GHz wireless',
                'Symmetrical honeycomb-shell mouse with a matte white finish.',
                'Sample record. Official product guide lists a 2.4 GHz receiver.'],
            ['MS-SEED036', 'Glorious', 'Model D Wireless', 'Matte White', '2.4GHz wireless',
                'Ergonomic right-handed honeycomb-shell mouse with six buttons.',
                'Sample record. Official product guide lists a 2.4 GHz receiver.'],
            ['MS-SEED037', 'MSI', 'Clutch GM41 Lightweight Wireless', 'Black', '2.4GHz wireless',
                'Symmetrical 74 g FPS mouse with a charging dock and a USB wireless transmitter.',
                'Sample record. MSI product page documents 2.4G RF with 1 ms latency.'],
            ['MS-SEED038', 'Gigabyte', 'AORUS M6', 'Matte Black', '2.4GHz wireless',
                'Lightweight gaming mouse with an AORUS emblem and a USB-A 2.4 GHz dongle.',
                'Sample record. GIGABYTE specifications list USB-C / 2.4 GHz wireless.'],
            ['MS-SEED039', 'Samsung', 'Wireless Mouse AA-SM7PWR', 'Silver', '2.4GHz wireless',
                'Slim silver office mouse with Blue Trace sensor and a tiny receiver.',
                'Sample record. Samsung accessory specifications document a 2.4G RF interface.'],
            ['MS-SEED040', 'LG', 'gram Wireless Mouse MSA2', 'Silver', '2.4GHz wireless',
                'Compact lightweight mouse matching the LG gram laptop colour scheme.',
                'Sample record. LG product page documents plug-and-play 2.4 GHz.'],
            ['MS-SEED041', 'Xiaomi', 'Xiaomi Wireless Mouse 3', 'White', '2.4GHz wireless / Bluetooth',
                'Minimalist white mouse with a mode button and a built-in Nano receiver slot.',
                'Sample record. Xiaomi FAQ documents one 2.4G and two Bluetooth channels.'],
            ['MS-SEED042', 'Huawei', 'HUAWEI Bluetooth Mouse (2nd generation) CD23', 'Grey', 'Bluetooth',
                'Slim grey mouse with a concealed battery compartment and no dongle.',
                'Sample record. Huawei support lists CD23 / CD23 SE variants.'],
            ['MS-SEED043', 'Anker', 'Anker 2.4G Wireless Vertical Ergonomic Mouse A7852', 'Black', '2.4GHz wireless',
                'Upright vertical mouse with a handshake grip and three DPI levels.',
                'Sample record. Anker product page documents the model-specific A7890 receiver.'],
            ['MS-SEED044', 'Baseus', 'Baseus F01B Tri-Mode Wireless Mouse', 'Frosted Grey', '2.4GHz wireless / Bluetooth',
                'Compact mouse with a tri-mode switch and a frosted plastic shell.',
                'Sample record. Product page documents Bluetooth 5.0 and 2.4 GHz.'],
            ['MS-SEED045', 'Ugreen', 'UGREEN Dual-Mode Wireless Mouse 90531', 'Starry Black', '2.4GHz wireless / Bluetooth',
                'Slim silent-click mouse with a small receiver stored under the battery cover.',
                'Sample record. UGREEN specifications give model 90531 / MU001.'],
            ['MS-SEED046', 'CHERRY', 'R-300 Wireless Mouse', 'Black', '2.4GHz wireless',
                'Compact black office mouse with a small plug-and-play receiver.',
                'Sample record. 046A:0106 documents the receiver, not the mouse.'],
            ['MS-SEED047', 'Fujitsu', 'Wireless Notebook Mouse WI610', 'Black', '2.4GHz wireless',
                'Midi-size notebook mouse with a switchable high-resolution sensor.',
                'Sample record. Fujitsu page documents 2.4 GHz wireless.'],
            ['MS-SEED048', 'Philips', 'Wireless mouse SPK7384/00', 'Black', '2.4GHz wireless',
                'Three-button black office mouse with a compact optical sensor.',
                'Sample record. Philips product sheet documents 2.4GHz wireless.'],
            ['MS-SEED049', 'Belkin', 'Wireless Keyboard and Mouse Combo BBZ010tt', 'Black', '2.4GHz wireless',
                'Ambidextrous three-button mouse with a contoured shape and a shared USB-A receiver.',
                'Sample record. Belkin page documents 2.4 GHz wireless.'],
            ['MS-SEED050', 'Targus', 'Wireless Blue Trace Mouse AMW50US', 'Black', '2.4GHz wireless',
                'Travel mouse with a Stow-N-Go receiver that parks inside the shell.',
                'Sample record. Targus page documents 2.4 GHz with a 10 m range.'],
            ['MS-SEED051', 'Kensington', 'Pro Fit Ergo Wireless Mouse', 'Grey', '2.4GHz wireless / Bluetooth',
                'Curved ergonomic mouse with a rubberised grip and dual-mode switch.',
                'Sample record. Kensington page documents 2.4 GHz and Bluetooth LE.'],
            ['MS-SEED052', 'E-Blue', 'Mazer II Type-R EMS601 Wireless Gaming Mouse', 'Black', '2.4GHz wireless',
                'Right-handed gaming mouse with blue illuminated accents and a chunky receiver.',
                'Sample record. Reseller listing documents model EMS601 with 2.4 GHz.'],
            ['MS-SEED053', 'Fantech', 'Aria XD7', 'White', '2.4GHz wireless / Bluetooth',
                'Egg-shaped 59 g gaming mouse with a PixArt sensor and tri-mode switch.',
                'Sample record. Fantech page documents BT5.0 / wired / 2.4 GHz.'],
            ['MS-SEED054', 'Rexus', 'Arka II RX-107', 'Black', '2.4GHz wireless',
                'Ergonomic gaming mouse with RGB lighting and a PAW3311 sensor.',
                'Sample record. Rexus page documents a dual-connection wireless mouse.'],
            ['MS-SEED055', 'Tecware', 'EX2 Professional Gaming Mouse', 'Black', '2.4GHz wireless / Bluetooth',
                'Lightweight 63 g mouse with PTFE feet and a compact dongle.',
                'Sample record. Tecware page documents 2.4 GHz + Bluetooth.'],
            ['MS-SEED056', 'Inphic', 'Inphic PM6 Pro Wireless Mouse', 'Grey', '2.4GHz wireless / Bluetooth',
                'Slim multi-device mouse with a visible battery indicator.',
                'Sample record. Inphic catalogue documents 2.4 GHz and Bluetooth.'],
            ['MS-SEED057', 'Xtrfy', 'M42 Wireless', 'Black', '2.4GHz wireless',
                'Symmetrical ultra-light esports mouse with an adjustable shell.',
                'Sample record. Official page documents 2.4 GHz and a bundled dongle.'],
            ['MS-SEED058', 'Akko', 'AG325W Wireless Gaming Mouse', 'Pink', '2.4GHz wireless / Bluetooth',
                'Compact gaming mouse with a playful colourway and a dual-mode switch.',
                'Sample record. Akko page documents Bluetooth + 2.4G dual mode.'],
            ['MS-SEED059', 'A4Tech Bloody', 'Bloody R72 Ultra', 'Black', '2.4GHz wireless',
                'Dual-mode gaming mouse with a large 2.4G receiver and a braided charging cable.',
                'Sample record. Product listings document 2.4 GHz / USB-C dual mode.'],
            ['MS-SEED060', 'Sunsonny', 'Sunsonny SR8 Wireless Optical Mouse', 'Black', '2.4GHz wireless',
                'Rounded office/gaming mouse with a small Nano receiver.',
                'Sample record. Official Sunsonny driver page lists the SR8 wireless mouse.'],
            ['MS-SEED061', 'Fujitsu-Siemens', 'Fujitsu Siemens Mouse Esprimo Q', 'Black', 'Unknown wireless',
                'Small black mouse from the Esprimo Q desktop accessory family.',
                'Sample record. usb.ids documents 04D9:2206 but not the radio; kept unknown.'],
            ['MS-SEED062', 'Genius', 'Genius Ergo 9000S Pro', 'Silver', '2.4GHz wireless / Bluetooth',
                'Compact ergonomic silent-click mouse with a thumb rest and USB-C charging.',
                'Sample record. Genius page documents Bluetooth 5.0 and 2.4 GHz.'],
            ['MS-SEED063', 'Perixx', 'PERIMICE-803A Wireless Multi-Device Mouse', 'Black', '2.4GHz wireless / Bluetooth',
                'Mid-size ergonomic mouse with a receiver stored in the battery compartment.',
                'Sample record. Perixx page documents 2.4 GHz, BT5.0 and BT3.0.'],
            ['MS-SEED064', 'Verbatim', 'Go Nano Wireless Mouse', 'Black', '2.4GHz wireless',
                'Three-button mouse with a textured grip and a tiny leave-in Nano receiver.',
                'Sample record. Verbatim page documents 2.4 GHz RF.'],
            ['MS-SEED065', 'Hama', 'WM-450 Multi-Device Mouse', 'Blue', '2.4GHz wireless / Bluetooth',
                'Seven-button multi-device mouse with a blue top shell.',
                'Sample record. Hama page documents one radio and two Bluetooth channels.'],
        ];

        return array_map(static fn (array $row): array => [
            'mouse_id' => $row[0],
            'brand' => $row[1],
            'model' => $row[2],
            'color' => $row[3],
            'connectivity_type' => $row[4],
            'physical_description' => $row[5],
            'notes' => $row[6],
        ], $rows);
    }

    /** @return array<int, array<string, string|null>> */
    private function dongleRows(): array
    {
        // [id, brand, model, receiver type, colour, vid, pid, physical description, notes]
        $rows = [
            ['DG-SEED001', 'Logitech', 'Logi Bolt Receiver', 'Bolt', 'Black', '046D', 'C548',
                'Tiny black USB-A receiver with a rounded tip and a small Logitech mark.',
                'Development sample. VID:PID documented in usb.ids as "Logi Bolt Receiver"; pairs only with Logi Bolt devices.'],
            ['DG-SEED002', 'Logitech', 'Unifying Receiver', 'Unifying', 'Grey', '046D', 'C52B',
                'Small grey USB-A receiver with an orange Unifying star logo.',
                'Development sample. VID:PID documented in usb.ids and the HID++ specification excerpt; up to six compatible devices.'],
            ['DG-SEED003', 'Logitech', 'Nano Receiver', 'Nano', 'Black', '046D', 'C534',
                'Very small black USB-A receiver with a flat end.',
                'Development sample. VID:PID documented in usb.ids as "Nano Receiver"; usually factory-paired to one device.'],
            ['DG-SEED004', 'Logitech', 'Lightspeed Receiver', 'Lightspeed', 'Black', '046D', 'C539',
                'Small black USB-A receiver with a matte finish and no visible model label.',
                'Development sample. VID:PID documented in usb.ids as "Lightspeed Receiver"; compatible models must be checked.'],
            ['DG-SEED005', 'Razer', 'Viper Ultimate Wireless Dongle', 'HyperSpeed', 'Black', '1532', '007B',
                'Stubby black USB-A dongle with the Razer snake logo.',
                'Development sample. VID:PID documented in usb.ids/DeviceHunt as the Viper Ultimate dongle.'],
            ['DG-SEED006', 'Razer', 'Basilisk Ultimate Wireless Receiver', 'HyperSpeed', 'Black', '1532', '0088',
                'Black USB-A receiver with a small embossed Razer logo.',
                'Development sample. VID:PID documented in usb.ids as "Basilisk Ultimate, Wireless, Receiver".'],
            ['DG-SEED007', 'Microsoft', '2.4GHz Transceiver v8.0', 'Proprietary 2.4GHz', 'Black', '045E', '07B2',
                'Low-profile black USB-A transceiver with no external antenna.',
                'Development sample. VID:PID documented in usb.ids as used by the Wireless Desktop 900 mouse.'],
            ['DG-SEED008', 'Microsoft', 'Nano Transceiver v1.0 for Bluetooth', 'Bluetooth Nano', 'Black', '045E', '0745',
                'Small black USB-A transceiver with a Microsoft badge.',
                'Development sample. VID:PID documented in usb.ids; Bluetooth-class transceiver in the 2.4 GHz ISM band.'],
            ['DG-SEED009', 'Corsair', 'Harpoon Wireless Dongle', 'Slipstream', 'Black', '1B1C', '1B65',
                'Compact black USB-A dongle with a small CORSAIR mark.',
                'Development sample. VID:PID documented in usb.ids; SLIPSTREAM family from the official Corsair page.'],
            ['DG-SEED010', 'SteelSeries', '2.4 GHz mouse receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Stubby black USB-A receiver with a SteelSeries dot embossed on the underside.',
                'Development sample. No SteelSeries mouse-receiver PID in this usb.ids snapshot; VID/PID left null.'],
            ['DG-SEED011', 'ASUS', 'ROG SpeedNova receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Small black USB-A dongle with a red ROG accent stripe.',
                'Development sample. SpeedNova 2.4 GHz documented by ASUS; no receiver PID in usb.ids.'],
            ['DG-SEED012', 'HP', 'Wireless Keyboard and Optical Mouse receiver', 'Proprietary 2.4GHz', 'Black', '03F0', '0B0C',
                'Plain black USB-A receiver with a tiny HP stamp on the underside.',
                'Development sample. VID:PID and product name documented verbatim in usb.ids.'],
            ['DG-SEED013', 'Dell', 'Universal Receiver', 'Proprietary 2.4GHz', 'Black', '413C', '2501',
                'Small black USB-A dongle with a Dell circular logo.',
                'Development sample. VID:PID and product name documented verbatim in usb.ids.'],
            ['DG-SEED014', 'Lenovo', 'Wireless Dongle for Keyboard and Mouse', 'Proprietary 2.4GHz', 'Black', '17EF', '6032',
                'Small black USB-A dongle with a Lenovo label.',
                'Development sample. VID:PID and product name documented verbatim in usb.ids.'],
            ['DG-SEED015', 'Sony', 'VAIO VGP-WMS30 bundled receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Slim black USB-A receiver with a small VAIO badge.',
                'Development sample. No Sony receiver PID in this usb.ids snapshot; brand from the VAIO wireless mouse bundle.'],
            ['DG-SEED016', 'Apple', 'Built-in Bluetooth controller (integrated)', 'Bluetooth', 'Silver', null, null,
                'No physical dongle: a Magic Mouse pairs to the host computer\'s internal Bluetooth radio.',
                'Development sample. Apple does not sell a mouse dongle; this row documents the integrated radio stand-in honestly.'],
            ['DG-SEED017', 'Trust', 'Trust 2.4 GHz mouse receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Small black USB-A receiver with a white Trust lettering.',
                'Development sample. No Trust receiver PID in this usb.ids snapshot; left null.'],
            ['DG-SEED018', 'A4Tech', 'RXM-15 RF Receiver', 'Proprietary 2.4GHz', 'Black', '09DA', '001A',
                'Slightly worn black USB-A receiver labelled RXM-15.',
                'Development sample. 09DA:001A documented in usb.ids as "Wireless Mouse & RXM-15 Receiver".'],
            ['DG-SEED019', 'Rapoo', 'Rapoo 5 GHz Wireless Receiver', 'Proprietary 5GHz', 'Black', '24AE', '2001',
                'Black USB-A receiver with a small embossed Rapoo logo.',
                'Development sample. 24AE:2001 is documented in usb.ids as a "5 GHz Wireless Receiver"; band taken verbatim, not assumed.'],
            ['DG-SEED020', 'Keychron', 'Keychron 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Tiny black USB-A dongle with no visible branding.',
                'Development sample. No Keychron receiver PID in this usb.ids snapshot; left null.'],
            ['DG-SEED021', 'HyperX', 'HyperX 2.4 GHz wireless receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Stubby black USB-A dongle with a small HyperX mark.',
                'Development sample. 0951:16C4 in usb.ids is a Cloud Flight headset, so no mouse PID is claimed.'],
            ['DG-SEED022', 'Redragon', 'Redragon 2.4 GHz gaming receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Black USB-A dongle with a red accent and no model label.',
                'Development sample. 04D9:2519 is a generic LogoTech receiver, not a documented Redragon PID; left null.'],
            ['DG-SEED023', 'Glorious', 'Glorious 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Matte black USB-A receiver sized to stay plugged in.',
                'Development sample. Official Glorious product guide lists a 2.4 GHz receiver; no PID in usb.ids.'],
            ['DG-SEED024', 'MSI', 'Clutch GM41 2.4G transmitter dongle', 'Proprietary 2.4GHz', 'Black', null, null,
                'Small black USB-A dongle supplied with the GM41 charging dock.',
                'Development sample. MSI product page documents a USB wireless 2.4G transmitter; no PID in usb.ids.'],
            ['DG-SEED025', 'Gigabyte', 'AORUS M6 2.4 GHz dongle', 'Proprietary 2.4GHz', 'Black', null, null,
                'Compact black USB-A dongle with an AORUS falcon mark.',
                'Development sample. GIGABYTE specifications document 2.4 GHz wireless; no PID in usb.ids.'],
            ['DG-SEED026', 'Samsung', 'Samsung 2.4G RF mouse receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Tiny black USB-A receiver with a Samsung stamp on the back.',
                'Development sample. Samsung accessory page documents 2.4G RF; no PID in usb.ids.'],
            ['DG-SEED027', 'LG', 'LG gram mouse receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Low-profile black USB-A receiver matching the LG gram mouse.',
                'Development sample. LG page documents plug-and-play 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED028', 'Xiaomi', 'Xiaomi 2.4G Nano receiver', 'Nano', 'White', null, null,
                'Very small white USB-A receiver stored inside the mouse battery compartment.',
                'Development sample. Xiaomi FAQ documents a 2.4G channel; no Xiaomi mouse PID in usb.ids.'],
            ['DG-SEED029', 'Huawei', 'Huawei 2.4G wireless receiver', 'Proprietary 2.4GHz', 'Grey', null, null,
                'Grey USB-A receiver matching the HUAWEI Wireless Mouse Pro Edition.',
                'Development sample. Huawei support lists the WYN Pro model; no PID in usb.ids.'],
            ['DG-SEED030', 'Anker', 'Anker A7890 receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Small black USB-A receiver marked A7890 on the underside.',
                'Development sample. Anker FAQ documents a model-specific A7890 receiver; no PID in usb.ids.'],
            ['DG-SEED031', 'Baseus', 'Baseus 2.4G receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Short black USB-A dongle with a Baseus logo.',
                'Development sample. Product pages document 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED032', 'Ugreen', 'UGREEN 2.4G receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Slim black USB-A receiver with a UGREEN mark.',
                'Development sample. UGREEN specifications document 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED033', 'CHERRY', 'R-300 Wireless Mouse Receiver', 'Proprietary 2.4GHz', 'Black', '046A', '0106',
                'Small black USB-A receiver with a CHERRY logo.',
                'Development sample. VID:PID documented verbatim in usb.ids as "R-300 Wireless Mouse Receiver".'],
            ['DG-SEED034', 'Fujitsu', 'Fujitsu 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Plain black USB-A receiver with a Fujitsu label.',
                'Development sample. Fujitsu product page documents 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED035', 'Philips', 'Philips 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Black USB-A receiver with a small Philips badge.',
                'Development sample. Philips product sheet documents 2.4GHz wireless; no PID in usb.ids.'],
            ['DG-SEED036', 'Belkin', 'Belkin 2.4GHz Nano receiver', 'Nano', 'Black', null, null,
                'Tiny black USB-A receiver with a Belkin stamp.',
                'Development sample. Belkin page documents a 2.4 GHz Nano receiver; no PID in usb.ids.'],
            ['DG-SEED037', 'Targus', 'Stow-N-Go USB receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Black USB-A receiver with a small retractable slot.',
                'Development sample. Targus page documents a Stow-N-Go 2.4 GHz receiver; no PID in usb.ids.'],
            ['DG-SEED038', 'Kensington', 'Kensington 2.4GHz Nano receiver', 'Nano', 'Black', null, null,
                'Small black USB-A dongle with a Kensington lock-shaped mark.',
                'Development sample. Kensington page documents a 2.4 GHz USB-A Nano dongle; no PID in usb.ids.'],
            ['DG-SEED039', 'E-Blue', 'E-Blue 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Chunky black USB-A receiver with blue LED trim.',
                'Development sample. E-Blue EMS601 listings document 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED040', 'Fantech', 'Fantech 2.4 GHz dongle', 'Proprietary 2.4GHz', 'Black', null, null,
                'Small black USB-A dongle with a Fantech logo.',
                'Development sample. Fantech Aria XD7 page documents 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED041', 'Rexus', 'Rexus 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Black USB-A dongle with a red Rexus accent.',
                'Development sample. Rexus product pages document 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED042', 'Tecware', 'Tecware 2.4 GHz dongle', 'Proprietary 2.4GHz', 'Black', null, null,
                'Small matte-black USB-A dongle with no label.',
                'Development sample. Tecware EX2 page documents 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED043', 'Inphic', 'Inphic 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Tiny black USB-A receiver with an Inphic sticker.',
                'Development sample. Inphic pages document 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED044', 'Xtrfy', 'XTRFY M42 Wireless dongle', 'Proprietary 2.4GHz', 'Black', null, null,
                'Black USB-A dongle with an XTRFY cross logo.',
                'Development sample. Official product page lists an adapter and dongle; no PID in usb.ids.'],
            ['DG-SEED045', 'Akko', 'Akko 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Small black USB-A dongle with an Akko star.',
                'Development sample. Akko pages document 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED046', 'A4Tech Bloody', 'Bloody 2.4G gaming receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Larger black USB-A receiver with a red Bloody emblem.',
                'Development sample. Bloody R72 Ultra listings document a dual 2.4G gaming receiver; no PID in usb.ids.'],
            ['DG-SEED047', 'Sunsonny', 'Sunsonny 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Black USB-A dongle with a Sunsonny logo.',
                'Development sample. Sunsonny SR8 driver page documents the wireless mouse; no PID in usb.ids.'],
            ['DG-SEED048', 'Fujitsu-Siemens', 'Fujitsu Siemens Esprimo receiver', 'Unknown', 'Black', null, null,
                'Older black USB-A receiver from an Esprimo Q desktop bundle.',
                'Development sample. usb.ids documents a mouse PID but no receiver PID or radio; identity kept minimal.'],
            ['DG-SEED049', 'Genius', 'Genius 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Small black USB-A dongle with a Genius logo.',
                'Development sample. Genius page documents 2.4 GHz; no PID in usb.ids.'],
            ['DG-SEED050', 'Perixx', 'PERIMICE 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Long black USB-A dongle with a Perixx label.',
                'Development sample. Perixx page documents a stored 2.4 GHz receiver; no PID in usb.ids.'],
            ['DG-SEED051', 'Verbatim', 'Verbatim Nano receiver', 'Nano', 'Black', null, null,
                'Tiny black USB-A receiver with a Verbatim wordmark.',
                'Development sample. Verbatim page documents a plug-in Nano receiver; no PID in usb.ids.'],
            ['DG-SEED052', 'Hama', 'Hama 2.4 GHz receiver', 'Proprietary 2.4GHz', 'Black', null, null,
                'Black USB-A dongle with a small Hama logo.',
                'Development sample. Hama pages document 2.4 GHz radio; no PID in usb.ids.'],
            ['DG-SEED053', null, 'SPRF2413A 2.4GHz receiver', 'Proprietary 2.4GHz', 'Black', '1BCF', '05C5',
                'Generic black USB-A receiver with no legible branding.',
                'Development sample. VID:PID documented in usb.ids as a Sunplus 2.4GHz keyboard/mouse receiver, but the physical brand is genuinely unknown.'],
            ['DG-SEED054', null, null, null, 'Black', null, null,
                'Unmarked black USB-A receiver; no label, logo or readable hardware ID.',
                'Development sample. Genuinely unknown dongle - brand/model/VID/PID intentionally null. Never guess an identity.'],
            ['DG-SEED055', null, null, null, 'White', null, null,
                'Tiny white USB-A receiver with a worn-off marking.',
                'Development sample. Genuinely unknown dongle - left uninvestigated on purpose.'],
            ['DG-SEED056', null, null, null, 'Grey', null, null,
                'Grey USB-A receiver with a faint faded label; text is not readable.',
                'Development sample. Awaiting a real USB scan and physical test; no identity is asserted.'],
        ];

        return array_map(static fn (array $row): array => [
            'dongle_id' => $row[0],
            'brand' => $row[1],
            'model' => $row[2],
            'receiver_type' => $row[3],
            'color' => $row[4],
            'vid' => $row[5],
            'pid' => $row[6],
            'hardware_id' => $row[5] !== null && $row[6] !== null
                ? 'USB\\VID_'.$row[5].'&PID_'.$row[6]
                : null,
            'serial_number' => null,
            'physical_description' => $row[7],
            'notes' => $row[8],
        ], $rows);
    }

    /**
     * Per-dongle research details. Keys not present fall back to receiver-type defaults in
     * seedIdentifications(). All URLs below are real official or reference destinations.
     *
     * @return array<string, array<string, mixed>>
     */
    private function identificationMeta(): array
    {
        return [
            'DG-SEED001' => [
                'technology' => 'Proprietary 2.4 GHz (Logi Bolt)', 'family' => 'Logi Bolt',
                'utility' => 'Logi Options+', 'support_url' => self::LOGI_BOLT,
                'notes' => 'Logi Bolt products cannot be paired to Unifying receivers. Compatibility remains a documentation lead.',
                'extra_sources' => [[
                    'title' => 'Logi Bolt wireless technology', 'url' => self::LOGI_BOLT,
                    'source' => 'logitech.com', 'official' => true, 'category' => 'technology',
                    'snippet' => 'Official description of the secure 2.4 GHz Logi Bolt link.',
                ]],
            ],
            'DG-SEED002' => [
                'technology' => 'Proprietary 2.4 GHz (Logitech Unifying)', 'family' => 'Unifying',
                'utility' => 'Logitech Unifying Software / Logi Options+', 'support_url' => self::LOGITECH_UNIFYING_ARTICLE,
                'notes' => 'Unifying can host up to six compatible devices; a shared VID:PID never proves which mouse is paired.',
                'extra_sources' => [[
                    'title' => 'Logitech Unifying receiver overview', 'url' => self::WIKI_UNIFYING,
                    'source' => 'wikipedia.org', 'official' => false, 'category' => 'reference',
                    'snippet' => 'Small USB receiver using a 2.4 GHz radio and Logitech Unifying pairing software.',
                ]],
            ],
            'DG-SEED003' => [
                'technology' => 'Proprietary 2.4 GHz (Logitech Nano)', 'family' => 'Nano',
                'utility' => 'Logi Options+ where supported', 'support_url' => self::LOGI_OPTIONS,
                'notes' => 'Nano receivers are ordinarily factory-paired to one device; physical test required.',
            ],
            'DG-SEED004' => [
                'technology' => 'Proprietary 2.4 GHz (Logitech LIGHTSPEED)', 'family' => 'Lightspeed',
                'utility' => 'Logitech G HUB', 'support_url' => self::LOGI_OPTIONS,
                'notes' => 'Compatible-model names are documentation leads, not a verified pairing.',
            ],
            'DG-SEED005' => [
                'technology' => 'Proprietary 2.4 GHz (Razer HyperSpeed)', 'family' => 'HyperSpeed',
                'utility' => 'Razer Synapse', 'support_url' => self::RAZER_SYNAPSE,
                'notes' => 'usb.ids explicitly names this PID as the Viper Ultimate dongle; no other mouse is asserted.',
                'extra_sources' => [[
                    'title' => 'Razer HyperSpeed Wireless technology', 'url' => self::RAZER_HYPERSPEED,
                    'source' => 'razer.com', 'official' => true, 'category' => 'technology',
                    'snippet' => 'Official description of Razer low-latency 2.4 GHz wireless technology.',
                ]],
            ],
            'DG-SEED006' => [
                'technology' => 'Proprietary 2.4 GHz (Razer HyperSpeed)', 'family' => 'HyperSpeed',
                'utility' => 'Razer Synapse', 'support_url' => self::RAZER_SYNAPSE,
                'notes' => 'Exact receiver PID documented in usb.ids; physical test still required.',
            ],
            'DG-SEED007' => [
                'technology' => 'Proprietary 2.4 GHz', 'family' => 'Microsoft 2.4GHz',
                'utility' => 'Microsoft Mouse and Keyboard Center', 'support_url' => 'https://www.microsoft.com/',
                'notes' => 'usb.ids names this receiver as used by the Wireless Desktop 900 mouse.',
            ],
            'DG-SEED008' => [
                'technology' => self::TECH_BT, 'frequency' => self::FREQ_BT, 'family' => 'Bluetooth Nano',
                'utility' => self::UTILITY_OS_BT, 'support_url' => 'https://www.microsoft.com/',
                'notes' => 'Bluetooth-class transceiver; compatible models are leads only, not proof of a pairing.',
            ],
            'DG-SEED009' => [
                'technology' => 'Proprietary 2.4 GHz (CORSAIR SLIPSTREAM)', 'family' => 'Slipstream',
                'utility' => 'CORSAIR iCUE', 'support_url' => self::CORSAIR_ICUE,
                'notes' => 'VID:PID documented in usb.ids; SLIPSTREAM utility and 2.4 GHz band from official Corsair pages.',
                'extra_sources' => [[
                    'title' => 'SLIPSTREAM CORSAIR WIRELESS TECHNOLOGY', 'url' => self::CORSAIR_SLIPSTREAM,
                    'source' => 'corsair.com', 'official' => true, 'category' => 'technology',
                    'snippet' => 'Official description of Corsair 2.4 GHz gaming-grade wireless.',
                ]],
            ],
            'DG-SEED011' => [
                'technology' => 'Proprietary 2.4 GHz (ROG SpeedNova)', 'family' => 'ROG SpeedNova',
                'utility' => 'ASUS Armoury Crate', 'support_url' => self::ASUS_SPEEDNOVA,
                'notes' => 'ASUS documents the 2.4 GHz SpeedNova link, but no receiver PID exists in usb.ids; confidence is medium.',
            ],
            'DG-SEED012' => [
                'technology' => 'Proprietary 2.4 GHz', 'family' => 'HP 2.4GHz',
                'utility' => 'HP Support Assistant', 'support_url' => 'https://support.hp.com/',
                'notes' => 'Documented VID:PID and product name; family and band are documentation-level.',
            ],
            'DG-SEED013' => [
                'technology' => 'Proprietary 2.4 GHz (Dell Universal Pairing)', 'family' => 'Dell Universal Pairing',
                'utility' => 'Dell Universal Pairing utility', 'support_url' => 'https://www.dell.com/support/',
                'notes' => 'Dell documentation states up to six compatible devices can be added; VID:PID documented in usb.ids.',
            ],
            'DG-SEED014' => [
                'technology' => 'Proprietary 2.4 GHz', 'family' => 'Lenovo 2.4GHz',
                'utility' => 'Lenovo Settings', 'support_url' => 'https://pcsupport.lenovo.com/',
                'notes' => 'VID:PID documented verbatim; compatible mouse is a documentation lead only.',
            ],
            'DG-SEED016' => [
                'technology' => self::TECH_BT, 'frequency' => self::FREQ_BT, 'family' => 'Bluetooth (integrated)',
                'utility' => 'macOS Bluetooth settings', 'support_url' => 'https://support.apple.com/',
                'notes' => 'No physical Apple mouse dongle exists; this sample documents the host Bluetooth radio instead.',
            ],
            'DG-SEED018' => [
                'technology' => 'Proprietary 2.4 GHz (A4Tech RF)', 'family' => 'A4Tech RF',
                'utility' => self::UTILITY_NONE, 'support_url' => 'https://www.a4tech.com/support/',
                'notes' => 'usb.ids documents 09DA:001A as a combined receiver/mouse entry.',
            ],
            'DG-SEED019' => [
                'technology' => 'Proprietary 5 GHz', 'frequency' => '5 GHz (usb.ids label)', 'family' => 'Rapoo 5GHz',
                'utility' => self::UTILITY_NONE, 'support_url' => 'https://www.rapoo.com/',
                'notes' => 'The 5 GHz band is taken verbatim from the usb.ids product name; do not assume 2.4 GHz here.',
            ],
            'DG-SEED033' => [
                'technology' => 'Proprietary 2.4 GHz (CHERRY)', 'family' => 'CHERRY 2.4GHz',
                'utility' => self::UTILITY_NONE, 'support_url' => 'https://www.cherry.de/',
                'notes' => 'VID:PID documented verbatim as the R-300 receiver; no compatible-model claim is made.',
            ],
            'DG-SEED053' => [
                'technology' => 'Proprietary 2.4 GHz', 'family' => 'Generic 2.4GHz',
                'utility' => null, 'support_url' => self::USB_IDS,
                'notes' => 'VID:PID is documented but the physical dongle is unbranded; confidence reduced to medium.',
            ],
        ];
    }

    /** @return array<int, array{mouse_id: string, dongle_id: string, status: string, notes: string, days_ago: int}> */
    private function matchRows(): array
    {
        return [
            [
                'mouse_id' => 'MS-SEED006', 'dongle_id' => 'DG-SEED005', 'status' => 'matched', 'days_ago' => 2,
                'notes' => 'SAMPLE PAIR FOR UI DEMONSTRATION ONLY. VID:PID documented for the Viper Ultimate dongle, but no physical test was performed.',
            ],
            [
                'mouse_id' => 'MS-SEED012', 'dongle_id' => 'DG-SEED009', 'status' => 'matched', 'days_ago' => 4,
                'notes' => 'SAMPLE PAIR FOR UI DEMONSTRATION ONLY. Harpoon Wireless and its dongle share documented USB IDs; no real test is implied.',
            ],
            [
                'mouse_id' => 'MS-SEED001', 'dongle_id' => 'DG-SEED001', 'status' => 'suspected', 'days_ago' => 6,
                'notes' => 'SAMPLE PAIR. Logi Bolt family lead from public documentation only; a physical pairing test is still required.',
            ],
            [
                'mouse_id' => 'MS-SEED002', 'dongle_id' => 'DG-SEED003', 'status' => 'suspected', 'days_ago' => 8,
                'notes' => 'SAMPLE PAIR. M185 is documented in usb.ids and Nano is its documented family; not a verified relationship.',
            ],
            [
                'mouse_id' => 'MS-SEED010', 'dongle_id' => 'DG-SEED008', 'status' => 'suspected', 'days_ago' => 11,
                'notes' => 'SAMPLE PAIR. Bluetooth transceiver lead only; brand-level suspicion must never be treated as confirmation.',
            ],
            [
                'mouse_id' => 'MS-SEED017', 'dongle_id' => 'DG-SEED012', 'status' => 'suspected', 'days_ago' => 14,
                'notes' => 'SAMPLE PAIR. HP receiver/product names are documented in usb.ids; compatibility requires a physical test.',
            ],
            [
                'mouse_id' => 'MS-SEED027', 'dongle_id' => 'DG-SEED018', 'status' => 'suspected', 'days_ago' => 17,
                'notes' => 'SAMPLE PAIR. A4Tech RXM-15 is documented together with the G6-20D; still unverified.',
            ],
            [
                'mouse_id' => 'MS-SEED046', 'dongle_id' => 'DG-SEED033', 'status' => 'suspected', 'days_ago' => 20,
                'notes' => 'SAMPLE PAIR. CHERRY R-300 receiver is documented in usb.ids; physical test not performed by this seeder.',
            ],
            [
                'mouse_id' => 'MS-SEED011', 'dongle_id' => 'DG-SEED007', 'status' => 'suspected', 'days_ago' => 23,
                'notes' => 'SAMPLE PAIR. Microsoft transceiver v8.0 is documented for the Wireless Desktop 900 set; unverified.',
            ],
            [
                'mouse_id' => 'MS-SEED033', 'dongle_id' => 'DG-SEED022', 'status' => 'suspected', 'days_ago' => 26,
                'notes' => 'SAMPLE PAIR. Redragon receiver PID is not documented, so this remains a brand-level lead only.',
            ],
        ];
    }

    /** @return array{brand: string, model: string, reason: string, confidence: string, source_url: string} */
    private function compatibleMouse(string $brand, string $model, string $sourceUrl): array
    {
        return [
            'brand' => $brand,
            'model' => $model,
            'reason' => 'Documentation-level compatibility lead for this receiver family; a physical pairing test is still required. This is development sample data.',
            'confidence' => 'medium',
            'source_url' => $sourceUrl,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function compatibleMiceForBrand(string $brand): array
    {
        $mice = [];
        foreach ($this->mouseProfileRows() as $profile) {
            if ($profile['brand'] !== $brand) {
                continue;
            }
            $mice[] = $this->compatibleMouse($brand, (string) $profile['model'], (string) ($profile['source'] ?? self::USB_IDS));
            if (count($mice) >= 2) {
                break;
            }
        }

        return $mice;
    }

    /**
     * @param  array<int, array<string, mixed>>  $compatibleMice
     * @param  array<int, array<string, mixed>>  $extraSources
     * @return array<string, mixed>
     */
    private function identificationData(
        ?string $vid,
        ?string $pid,
        string $manufacturer,
        string $model,
        string $technology,
        string $frequency,
        string $family,
        array $compatibleMice,
        ?string $utility,
        string $supportUrl,
        string $confidence,
        string $notes,
        array $extraSources = [],
    ): array {
        $hardwareId = $vid !== null && $pid !== null ? 'USB\\VID_'.$vid.'&PID_'.$pid : null;
        $productName = $vid !== null && $pid !== null ? $this->productName($vid, $pid) : null;
        $deviceHuntUrl = self::DEVICEHUNT.$vid.'/device/'.$pid;

        $evidence = [];
        if ($vid !== null && $pid !== null) {
            $evidence[] = [
                'type' => 'local_database',
                'detail' => 'VID:PID '.$vid.':'.$pid.' resolves to "'.($productName ?? $model).'" in the local usb.ids snapshot (2026.06.26).',
                'source' => self::USB_IDS,
            ];
            $evidence[] = [
                'type' => 'online',
                'detail' => 'DeviceHunt mirrors the usb.ids entry for '.$vid.':'.$pid.'.',
                'source' => $deviceHuntUrl,
            ];
        }
        $evidence[] = [
            'type' => 'curated_reference',
            'detail' => $family.' is documented as '.$technology.' operating in the '.$frequency.' band; official utility: '.($utility ?? 'none documented').'.',
            'source' => $supportUrl,
        ];

        $researchSources = [];
        if ($vid !== null && $pid !== null) {
            $researchSources[] = [
                'title' => 'USB ID Repository (usb.ids) - local snapshot 2026.06.26',
                'url' => self::USB_IDS,
                'source' => 'linux-usb.org',
                'official' => false,
                'category' => 'reference',
                'snippet' => 'Community-maintained Vendor ID / Product ID name database; source of all documented VID:PID values in this sample dataset.',
            ];
            $researchSources[] = [
                'title' => 'DeviceHunt: '.$vid.':'.$pid,
                'url' => $deviceHuntUrl,
                'source' => 'devicehunt.com',
                'official' => false,
                'category' => 'lookup',
                'snippet' => 'Human-readable lookup of the documented USB vendor and product identifier.',
            ];
        }
        $researchSources[] = [
            'title' => $manufacturer.' official support / pairing information',
            'url' => $supportUrl,
            'source' => $manufacturer,
            'official' => true,
            'category' => 'support',
            'snippet' => 'Official destination for pairing software and device support.',
        ];
        $researchSources = array_merge($researchSources, $extraSources);

        return [
            'vid' => $vid,
            'pid' => $pid,
            'hardware_id' => $hardwareId,
            'probable_manufacturer' => $manufacturer,
            'probable_model' => $model,
            'device_category' => 'wireless_mouse_receiver',
            'wireless_technology' => $technology,
            'frequency' => $frequency,
            'receiver_family' => $family,
            'compatible_mice' => $compatibleMice,
            'pairing_utility' => $utility,
            'official_support_url' => $supportUrl,
            'evidence' => $evidence,
            'confidence' => $confidence,
            'research_sources' => $researchSources,
            'notes' => 'DEVELOPMENT SAMPLE RESEARCH RECORD, assembled from public documentation. '.$notes,
        ];
    }

    private function productName(string $vid, string $pid): ?string
    {
        foreach ($this->usbSnapshot()['products'] as $product) {
            if ($product['vid'] === $vid && $product['pid'] === $pid) {
                return $product['product_name'];
            }
        }

        foreach ($this->usbProducts() as $product) {
            if ($product['vid'] === $vid && $product['pid'] === $pid) {
                return $product['product_name'];
            }
        }

        return null;
    }
}
