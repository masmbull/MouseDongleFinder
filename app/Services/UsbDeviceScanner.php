<?php

namespace App\Services;

use App\Services\Concerns\RunsWindowsProcess;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Reads present USB devices from the Windows PnP database.
 *
 * The scan is read-only: no driver is installed and no file is executed.
 */
class UsbDeviceScanner
{
    use RunsWindowsProcess;

    private const TIMEOUT_SECONDS = 40;

    /**
     * @return array{devices: array<int, array<string, mixed>>, error: string|null, scanned_at: string}
     */
    public function scan(): array
    {
        $scannedAt = now()->toIso8601String();

        if (PHP_OS_FAMILY !== 'Windows') {
            return [
                'devices' => [],
                'error' => 'USB scanning is only available on Windows. The PnP cmdlets used by this feature do not exist on this host.',
                'scanned_at' => $scannedAt,
            ];
        }

        $scriptPath = resource_path('ps/scan-usb.ps1');

        if (! is_file($scriptPath)) {
            Log::error('USB scan script is missing.', ['path' => $scriptPath]);

            return [
                'devices' => [],
                'error' => 'USB scan script is missing. Please reinstall the application files.',
                'scanned_at' => $scannedAt,
            ];
        }

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
            Log::error('USB scan process could not be started.', ['message' => $exception->getMessage()]);

            return [
                'devices' => [],
                'error' => 'USB scan could not be started: '.$exception->getMessage(),
                'scanned_at' => $scannedAt,
            ];
        }

        if (! $process->isSuccessful()) {
            $errorOutput = trim($process->getErrorOutput()) ?: 'Unknown PowerShell error.';
            Log::error('USB scan process failed.', ['error' => $errorOutput]);

            return [
                'devices' => [],
                'error' => 'USB scan failed: '.$errorOutput,
                'scanned_at' => $scannedAt,
            ];
        }

        $decoded = json_decode($process->getOutput(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('USB scan returned invalid JSON.', ['error' => json_last_error_msg()]);

            return [
                'devices' => [],
                'error' => 'USB scan returned data that could not be parsed.',
                'scanned_at' => $scannedAt,
            ];
        }

        $rawDevices = is_array($decoded) && array_is_list($decoded) ? $decoded : (is_array($decoded) ? [$decoded] : []);
        $devices = [];

        foreach ($rawDevices as $rawDevice) {
            if (is_array($rawDevice)) {
                $devices[] = $this->normalizeDevice($rawDevice, $scannedAt);
            }
        }

        return [
            'devices' => $devices,
            'error' => null,
            'scanned_at' => $scannedAt,
        ];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeDevice(array $raw, string $scannedAt): array
    {
        $instanceId = is_string($raw['device_instance_id'] ?? null)
            ? $raw['device_instance_id']
            : (is_string($raw['instance_id'] ?? null) ? $raw['instance_id'] : '');

        $vid = $this->normalizeId($raw['vid'] ?? null);
        $pid = $this->normalizeId($raw['pid'] ?? null);

        if ($vid === null || $pid === null) {
            $parsed = $this->parseIdsFromInstanceId($instanceId);
            $vid ??= $parsed['vid'];
            $pid ??= $parsed['pid'];
        }

        $hardwareIds = $raw['hardware_ids'] ?? [];

        return [
            'name' => is_string($raw['name'] ?? null) && $raw['name'] !== '' ? $raw['name'] : 'Unknown Device',
            'manufacturer' => $this->nullableString($raw['manufacturer'] ?? null),
            'description' => $this->nullableString($raw['description'] ?? null),
            'vid' => $vid,
            'pid' => $pid,
            'hardware_ids' => is_array($hardwareIds) ? array_values(array_map(fn ($id) => (string) $id, $hardwareIds)) : [],
            'device_instance_id' => $instanceId,
            'serial_number' => $this->nullableString($raw['serial_number'] ?? null),
            'usb_class' => $this->nullableString($raw['usb_class'] ?? $raw['class'] ?? null),
            'driver_name' => $this->nullableString($raw['driver_name'] ?? null),
            'driver_version' => $this->nullableString($raw['driver_version'] ?? null),
            'driver_provider' => $this->nullableString($raw['driver_provider'] ?? null),
            'status' => $this->nullableString($raw['status'] ?? null),
            'location' => $this->nullableString($raw['location'] ?? null),
            'category' => $this->nullableString($raw['category'] ?? null) ?? 'other',
            // This is a candidate hint only; it is never a confirmed identification.
            'is_receiver' => (bool) ($raw['is_receiver'] ?? false),
            'detected_at' => $this->nullableString($raw['detected_at'] ?? null) ?? $scannedAt,
        ];
    }

    /**
     * @return array{vid: string|null, pid: string|null}
     */
    private function parseIdsFromInstanceId(string $instanceId): array
    {
        $vid = null;
        $pid = null;

        if (preg_match('/VID_([0-9A-Fa-f]{4})/', $instanceId, $matches) === 1) {
            $vid = strtoupper($matches[1]);
        }

        if (preg_match('/PID_([0-9A-Fa-f]{4})/', $instanceId, $matches) === 1) {
            $pid = strtoupper($matches[1]);
        }

        return ['vid' => $vid, 'pid' => $pid];
    }

    private function normalizeId(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[0-9A-F]{4}$/', $value) === 1 ? $value : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? null : $value;
        }

        return is_int($value) || is_float($value) ? (string) $value : null;
    }
}
