<?php

namespace Tests\Feature;

use App\Models\UsbScan;
use App\Services\UsbDeviceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsbScanTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_endpoint_returns_devices_without_persisting_by_default(): void
    {
        $this->mock(UsbDeviceScanner::class)
            ->shouldReceive('scan')
            ->once()
            ->andReturn([
                'devices' => [$this->device()],
                'error' => null,
                'scanned_at' => '2026-11-21T10:00:00+00:00',
            ]);

        $response = $this->postJson('/api/usb-scans/scan', ['save' => false]);

        $response->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('scanned_at', '2026-11-21T10:00:00+00:00')
            ->assertJsonPath('devices.0.vid', '046D')
            ->assertJsonMissingPath('scan');

        $this->assertDatabaseCount('usb_scans', 0);
    }

    public function test_scan_endpoint_persists_when_save_is_true(): void
    {
        $this->mock(UsbDeviceScanner::class)
            ->shouldReceive('scan')
            ->once()
            ->andReturn([
                'devices' => [$this->device()],
                'error' => null,
                'scanned_at' => '2026-11-21T10:00:00+00:00',
            ]);

        $this->postJson('/api/usb-scans/scan', ['save' => true])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonStructure(['scan', 'devices', 'count']);

        $this->assertDatabaseCount('usb_scans', 1);
    }

    public function test_scan_endpoint_exposes_scanner_errors_without_inventing_devices(): void
    {
        $this->mock(UsbDeviceScanner::class)
            ->shouldReceive('scan')
            ->once()
            ->andReturn([
                'devices' => [],
                'error' => 'USB scanning is only available on Windows.',
                'scanned_at' => '2026-11-21T10:00:00+00:00',
            ]);

        $this->postJson('/api/usb-scans/scan')
            ->assertOk()
            ->assertJsonPath('count', 0)
            ->assertJsonPath('error', 'USB scanning is only available on Windows.');
    }

    public function test_store_uses_scanner_and_returns_existing_response_shape(): void
    {
        $this->mock(UsbDeviceScanner::class)
            ->shouldReceive('scan')
            ->once()
            ->andReturn([
                'devices' => [$this->device()],
                'error' => null,
                'scanned_at' => '2026-11-21T10:00:00+00:00',
            ]);

        $this->postJson('/api/usb-scans')
            ->assertCreated()
            ->assertJsonPath('count', 1)
            ->assertJsonStructure(['scan', 'devices', 'count']);

        $this->assertDatabaseCount('usb_scans', 1);
    }

    public function test_latest_endpoint_returns_most_recent_scan(): void
    {
        $older = UsbScan::factory()->create(['scanned_at' => now()->subDay()]);
        $newest = UsbScan::factory()->create(['scanned_at' => now()]);

        $this->getJson('/api/usb-scans/latest')
            ->assertOk()
            ->assertJsonPath('id', $newest->id);

        $this->assertNotSame($older->id, $newest->id);
    }

    public function test_scan_can_be_updated_and_deleted(): void
    {
        $scan = UsbScan::factory()->create();

        $this->putJson("/api/usb-scans/{$scan->id}", [
            'devices' => [$this->device()],
            'device_count' => 1,
        ])->assertOk()->assertJsonPath('device_count', 1);

        $this->deleteJson("/api/usb-scans/{$scan->id}")->assertOk();
        $this->assertModelMissing($scan);
    }

    /**
     * @return array<string, mixed>
     */
    private function device(): array
    {
        return [
            'name' => 'Logitech USB Receiver',
            'manufacturer' => 'Logitech',
            'description' => 'Unifying Receiver',
            'vid' => '046D',
            'pid' => 'C52B',
            'hardware_ids' => ['USB\\VID_046D&PID_C52B&REV_0101'],
            'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&ABC',
            'serial_number' => null,
            'usb_class' => 'HIDClass',
            'driver_name' => 'HID-compliant device',
            'driver_version' => '10.0.1',
            'driver_provider' => 'Microsoft',
            'status' => 'OK',
            'location' => 'Port_#0001.Hub_#0001',
            'category' => 'wireless_mouse_receiver',
            'is_receiver' => true,
            'detected_at' => '2026-11-21T10:00:00+00:00',
        ];
    }
}
