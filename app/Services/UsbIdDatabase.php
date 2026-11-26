<?php

namespace App\Services;

use App\Models\UsbProduct;
use App\Models\UsbVendor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Local-first VID/PID decoder. Curated seed rows work without any downloaded file.
 */
class UsbIdDatabase
{
    public const USB_IDS_URL = 'https://www.linux-usb.org/usb.ids';

    public const CACHE_KEY = 'usbids.last_updated';

    private const RELATIVE_PATH = 'usbids/usb.ids';

    /**
     * @return array{vid: string|null, pid: string|null, vendor_name: string|null, product_name: string|null, found: bool}
     */
    public function lookup(?string $vid, ?string $pid = null): array
    {
        $normalizedVid = $this->normalizeId($vid);
        $normalizedPid = $this->normalizeId($pid);

        $vendorName = null;
        $productName = null;

        if ($normalizedVid !== null) {
            $vendorName = UsbVendor::query()
                ->where('vid', $normalizedVid)
                ->value('vendor_name');

            if ($normalizedPid !== null) {
                $productName = UsbProduct::query()
                    ->where('vid', $normalizedVid)
                    ->where('pid', $normalizedPid)
                    ->value('product_name');
            }
        }

        return [
            'vid' => $normalizedVid,
            'pid' => $normalizedPid,
            'vendor_name' => $vendorName,
            'product_name' => $productName,
            'found' => $vendorName !== null || $productName !== null,
        ];
    }

    /**
     * Parse a standard usb.ids file from the local disk into uppercase database rows.
     *
     * @return array{vendors: int, products: int, source: string, error: string|null}
     */
    public function importFromLocalFile(): array
    {
        $disk = Storage::disk('local');

        if (! $disk->exists(self::RELATIVE_PATH)) {
            return [
                'vendors' => 0,
                'products' => 0,
                'source' => self::RELATIVE_PATH,
                'error' => 'No local usb.ids file found. Curated vendor/product rows remain available.',
            ];
        }

        $contents = $disk->get(self::RELATIVE_PATH);
        $lines = preg_split('/\R/u', (string) $contents) ?: [];

        $vendors = 0;
        $products = 0;
        $currentVid = null;

        foreach ($lines as $line) {
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Two tabs mark an interface row; it cannot be keyed by VID/PID alone.
            if (str_starts_with($line, "\t\t")) {
                continue;
            }

            if (preg_match('/^([0-9A-Fa-f]{4})\s+(.+)$/u', $line, $matches) === 1) {
                $currentVid = strtoupper($matches[1]);
                UsbVendor::query()->updateOrCreate(
                    ['vid' => $currentVid],
                    ['vendor_name' => trim($matches[2])],
                );
                $vendors++;

                continue;
            }

            if (preg_match('/^\t([0-9A-Fa-f]{4})\s+(.+)$/u', $line, $matches) === 1 && $currentVid !== null) {
                UsbProduct::query()->updateOrCreate(
                    ['vid' => $currentVid, 'pid' => strtoupper($matches[1])],
                    ['product_name' => trim($matches[2])],
                );
                $products++;
            }
        }

        return [
            'vendors' => $vendors,
            'products' => $products,
            'source' => self::RELATIVE_PATH,
            'error' => null,
        ];
    }

    /**
     * Download the current usb.ids list, import it and remember when it happened.
     *
     * @return array{last_updated: string|null, imported: array<string, mixed>, error: string|null, message: string|null}
     */
    public function refresh(): array
    {
        try {
            $response = Http::connectTimeout(5)
                ->timeout(30)
                ->get(self::USB_IDS_URL);

            if (! $response->successful()) {
                throw new \RuntimeException('USB ID repository returned HTTP '.$response->status().'.');
            }

            Storage::disk('local')->put(self::RELATIVE_PATH, $response->body());

            $imported = $this->importFromLocalFile();
            $lastUpdated = now()->toIso8601String();
            Cache::forever(self::CACHE_KEY, $lastUpdated);

            return [
                'last_updated' => $lastUpdated,
                'imported' => $imported,
                'error' => null,
                'message' => 'usb.ids imported successfully.',
            ];
        } catch (Throwable $exception) {
            Log::warning('usb.ids refresh failed.', ['message' => $exception->getMessage()]);

            return [
                'last_updated' => $this->lastUpdated(),
                'imported' => ['vendors' => 0, 'products' => 0, 'source' => self::RELATIVE_PATH],
                'error' => 'Could not refresh usb.ids: '.$exception->getMessage(),
                'message' => 'Offline curated VID/PID rows remain available.',
            ];
        }
    }

    public function lastUpdated(): ?string
    {
        $value = Cache::get(self::CACHE_KEY);

        return is_string($value) ? $value : null;
    }

    private function normalizeId(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[0-9A-F]{4}$/', $value) === 1 ? $value : null;
    }
}
