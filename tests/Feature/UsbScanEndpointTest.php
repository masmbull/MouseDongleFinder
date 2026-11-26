<?php

namespace Tests\Feature;

use App\Models\UsbScan;
use App\Services\UsbDeviceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** USB scan endpoint coverage; the scanner is always mocked, never real hardware. */
class UsbScanEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_scans_and_filters_by_minimum_device_count(): void
    {
        UsbScan::factory()->create(['device_count' => 1]);
        UsbScan::factory()->create(['device_count' => 5]);

        $this->getJson('/api/usb-scans')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total']);

        $this->getJson('/api/usb-scans?device_count=5')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.device_count', 5);
    }

    public function test_latest_show_update_and_destroy_are_supported(): void
    {
        $older = UsbScan::factory()->create(['scanned_at' => now()->subDay()]);
        $newest = UsbScan::factory()->create(['scanned_at' => now()]);

        $this->getJson('/api/usb-scans/latest')
            ->assertOk()
            ->assertJsonPath('id', $newest->id);

        $this->getJson("/api/usb-scans/{$older->id}")
            ->assertOk()
            ->assertJsonPath('id', $older->id);

        $this->putJson("/api/usb-scans/{$older->id}", [
            'device_count' => 9,
            'scanned_by' => 'tester',
        ])->assertOk()
            ->assertJsonPath('device_count', 9)
            ->assertJsonPath('scanned_by', 'tester');

        $this->deleteJson("/api/usb-scans/{$older->id}")
            ->assertOk()
            ->assertJsonPath('message', 'USB scan deleted successfully');

        $this->assertModelMissing($older);
        $this->assertModelExists($newest);
    }

    public function test_scan_with_save_false_returns_devices_without_persisting(): void
    {
        $this->mock(UsbDeviceScanner::class)
            ->shouldReceive('scan')
            ->once()
            ->andReturn([
                'devices' => [$this->device()],
                'error' => null,
                'scanned_at' => '2026-11-21T10:00:00+00:00',
            ]);

        $this->postJson('/api/usb-scans/scan', ['save' => false])
            ->assertOk()
            ->assertJsonPath('count', 1)
            ->assertJsonPath('devices.0.vid', '046D')
            ->assertJsonMissingPath('error')
            ->assertJsonMissingPath('scan');

        $this->assertDatabaseCount('usb_scans', 0);
    }

    public function test_scan_with_save_true_persists_the_scan(): void
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
            ->assertJsonPath('scan.device_count', 1)
            ->assertJsonStructure(['scan', 'devices', 'count', 'scanned_at']);

        $this->assertDatabaseCount('usb_scans', 1);
    }

    public function test_scan_with_error_reports_it_without_inventing_devices(): void
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

    public function test_store_persists_a_scan_and_returns_201(): void
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
