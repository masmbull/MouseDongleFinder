<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UsbScan;
use App\Services\LivePairingDetector;
use App\Services\UsbDeviceScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class UsbScanController extends Controller
{
    public function __construct(
        private UsbDeviceScanner $scanner,
        private LivePairingDetector $livePairingDetector,
    ) {}

    public function liveMatch(): JsonResponse
    {
        try {
            return response()->json($this->livePairingDetector->detect());
        } catch (Throwable $exception) {
            Log::error('Live mouse detection failed.', ['message' => $exception->getMessage()]);

            return response()->json([
                'scanned_at' => now()->toIso8601String(),
                'error' => 'Live detection failed: '.$exception->getMessage(),
                'dongles' => [],
                'unattributed_devices' => [],
                'message' => 'Live detection is unavailable.',
            ]);
        }
    }

    public function index(Request $request): JsonResponse
    {
        $query = UsbScan::query();

        if ($request->has('device_count')) {
            $query->where('device_count', '>=', $request->device_count);
        }

        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);

        return response()->json($query->latest()->paginate($perPage));
    }

    public function scan(Request $request): JsonResponse
    {
        $result = $this->runScan();
        $devices = $result['devices'];

        $response = [
            'devices' => $devices,
            'count' => count($devices),
            'scanned_at' => $result['scanned_at'],
        ];

        if ($result['error'] !== null) {
            $response['error'] = $result['error'];
        }

        if ($request->boolean('save')) {
            $response['scan'] = $this->persistScan($devices, $result['scanned_at'], $request);
        }

        return response()->json($response);
    }

    public function store(Request $request): JsonResponse
    {
        $result = $this->runScan();
        $devices = $result['devices'];
        $scan = $this->persistScan($devices, $result['scanned_at'], $request);

        $response = [
            'scan' => $scan,
            'devices' => $devices,
            'count' => count($devices),
        ];

        if ($result['error'] !== null) {
            $response['error'] = $result['error'];
        }

        return response()->json($response, 201);
    }

    public function latest(): JsonResponse
    {
        return response()->json(UsbScan::query()->latest('scanned_at')->first());
    }

    public function show(UsbScan $usb_scan): JsonResponse
    {
        return response()->json($usb_scan);
    }

    public function update(Request $request, UsbScan $usb_scan): JsonResponse
    {
        $validated = $request->validate([
            'devices' => ['sometimes', 'array'],
            'device_count' => ['sometimes', 'integer', 'min:0'],
            'scanned_at' => ['sometimes', 'date'],
            'scanned_by' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $usb_scan->update($validated);

        return response()->json($usb_scan);
    }

    public function destroy(UsbScan $usb_scan): JsonResponse
    {
        $usb_scan->delete();

        return response()->json(['message' => 'USB scan deleted successfully']);
    }

    /**
     * @return array{devices: array<int, array<string, mixed>>, error: string|null, scanned_at: string}
     */
    private function runScan(): array
    {
        try {
            return $this->scanner->scan();
        } catch (Throwable $exception) {
            Log::error('USB scan failed.', ['message' => $exception->getMessage()]);

            return [
                'devices' => [],
                'error' => 'USB scan failed: '.$exception->getMessage(),
                'scanned_at' => now()->toIso8601String(),
            ];
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $devices
     */
    private function persistScan(array $devices, string $scannedAt, Request $request): UsbScan
    {
        return UsbScan::create([
            'scan_id' => 'SCN-'.strtoupper(Str::random(10)),
            'devices' => $devices,
            'device_count' => count($devices),
            'scanned_at' => $scannedAt,
            'scanned_by' => $request->user()?->name ?? 'system',
        ]);
    }
}
