<?php

namespace Tests\Feature;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\Mouse;
use App\Models\MouseProfile;
use App\Services\LivePairingDetector;
use App\Services\UsbDeviceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * One-click auto-discovery coverage. Every USB/HID payload is synthetic and
 * both hardware scanners are always mocked; no real device or process is used.
 */
class AutoDiscoverTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_device_name_creates_mouse_dongle_and_suspected_match(): void
    {
        MouseProfile::factory()->create([
            'brand' => 'Logitech',
            'model' => 'MX Master 3S',
            'vid' => '046D',
            'pid' => 'C52B',
            'receiver_family' => 'Unifying',
        ]);
        $this->mockScans([$this->usbReceiver()], [$this->usbReceiver(), $this->liveMouse()]);

        $response = $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonStructure([
                'scanned_at', 'error', 'message',
                'discovered' => ['*' => [
                    'dongle' => ['vid', 'pid', 'name', 'port', 'dongle_id', 'auto_created'],
                    'device' => ['name', 'vid', 'pid', 'instance_id', 'class', 'status', 'hardware_ids', 'location'],
                    'identity' => ['brand', 'model', 'source', 'confidence'],
                    'mouse' => ['id', 'mouse_id', 'brand', 'model', 'photo_url', 'auto_created'],
                    'match' => ['id', 'status', 'notes'],
                    'evidence' => ['*' => ['type', 'detail', 'source']],
                    'needs_user_review',
                ]],
                'unattributed_devices' => ['*' => [
                    'name', 'vid', 'pid', 'instance_id', 'class', 'status', 'hardware_ids', 'location',
                ]],
            ])
            ->assertJsonPath('error', null)
            ->assertJsonCount(1, 'discovered')
            ->assertJsonPath('discovered.0.dongle.vid', '046D')
            ->assertJsonPath('discovered.0.dongle.pid', 'C52B')
            ->assertJsonPath('discovered.0.dongle.port', 'Port_#0001')
            ->assertJsonPath('discovered.0.dongle.auto_created', true)
            ->assertJsonPath('discovered.0.device.name', 'Logitech MX Master 3S')
            ->assertJsonPath('discovered.0.identity.brand', 'Logitech')
            ->assertJsonPath('discovered.0.identity.model', 'MX Master 3S')
            ->assertJsonPath('discovered.0.identity.source', 'device_name')
            ->assertJsonPath('discovered.0.identity.confidence', 'high')
            ->assertJsonPath('discovered.0.mouse.auto_created', true)
            ->assertJsonPath('discovered.0.mouse.brand', 'Logitech')
            ->assertJsonPath('discovered.0.match.status', 'suspected')
            ->assertJsonPath('discovered.0.needs_user_review', false)
            ->assertJsonPath('unattributed_devices', []);

        $this->assertDatabaseCount('mice', 1);
        $this->assertDatabaseCount('dongles', 1);
        $this->assertDatabaseCount('device_matches', 1);
        $this->assertNull(DeviceMatch::query()->firstOrFail()->matched_at);
        $this->assertSame('Unifying', Dongle::query()->firstOrFail()->receiver_type);
        $this->assertStringContainsString('Auto-discovered from Windows PnP', Dongle::query()->firstOrFail()->notes);
        $this->assertStringContainsString('No physical pairing test was performed', DeviceMatch::query()->firstOrFail()->notes);
        $evidenceTypes = array_column($response->json('discovered.0.evidence'), 'type');
        $this->assertContains('topology', $evidenceTypes);
        $this->assertContains('hardware_id', $evidenceTypes);
        $this->assertContains('device_name', $evidenceTypes);
    }

    public function test_generic_windows_name_falls_back_to_vid_pid_mouse_profile(): void
    {
        MouseProfile::factory()->create([
            'brand' => 'Logitech',
            'model' => 'MX Master 3S',
            'vid' => '046D',
            'pid' => 'C52B',
            'receiver_family' => 'Unifying',
        ]);
        DB::table('usb_products')->insert([
            ['vid' => '046D', 'pid' => 'C52B', 'product_name' => 'Unifying Receiver', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $genericMouse = $this->liveMouse([
            'name' => 'HID-compliant mouse',
            'class' => 'Mouse',
        ]);
        $this->mockScans([$this->usbReceiver()], [$this->usbReceiver(), $genericMouse]);

        $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonPath('discovered.0.device.name', 'HID-compliant mouse')
            ->assertJsonPath('discovered.0.identity.source', 'mouse_profile')
            ->assertJsonPath('discovered.0.identity.confidence', 'medium')
            ->assertJsonPath('discovered.0.identity.model', 'MX Master 3S')
            ->assertJsonPath('discovered.0.mouse.auto_created', true)
            ->assertJsonPath('discovered.0.match.status', 'suspected')
            ->assertJsonPath('discovered.0.needs_user_review', true);

        $this->assertDatabaseCount('mice', 1);
        $this->assertDatabaseCount('dongles', 1);
        $this->assertDatabaseCount('device_matches', 1);
    }

    public function test_identical_dongles_are_returned_as_ambiguous_candidates_without_writes(): void
    {
        $firstReceiver = $this->usbReceiver([
            'location' => 'Port_#0001',
            'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
        ]);
        $secondReceiver = $this->usbReceiver([
            'location' => 'Port_#0002',
            'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&3',
        ]);
        $mouse = $this->liveMouse();
        $this->mockScans([$firstReceiver, $secondReceiver], [$firstReceiver, $secondReceiver, $mouse]);

        $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonCount(2, 'discovered')
            ->assertJsonPath('discovered.0.dongle.port', 'Port_#0001')
            ->assertJsonPath('discovered.1.dongle.port', 'Port_#0002')
            ->assertJsonPath('discovered.0.dongle.dongle_id', null)
            ->assertJsonPath('discovered.0.dongle.auto_created', false)
            ->assertJsonPath('discovered.0.mouse', null)
            ->assertJsonPath('discovered.0.match', null)
            ->assertJsonPath('discovered.0.needs_user_review', true)
            ->assertJsonPath('discovered.1.needs_user_review', true);

        $this->assertDatabaseCount('mice', 0);
        $this->assertDatabaseCount('dongles', 0);
        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_existing_registered_mouse_is_reused_without_creating_a_duplicate(): void
    {
        $mouse = Mouse::factory()->create(['brand' => 'Logitech', 'model' => 'MX Master 3S']);
        $this->mockScans([$this->usbReceiver()], [$this->usbReceiver(), $this->liveMouse()]);

        $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonPath('discovered.0.identity.source', 'registered_mouse')
            ->assertJsonPath('discovered.0.identity.confidence', 'high')
            ->assertJsonPath('discovered.0.mouse.id', $mouse->id)
            ->assertJsonPath('discovered.0.mouse.mouse_id', $mouse->mouse_id)
            ->assertJsonPath('discovered.0.mouse.auto_created', false)
            ->assertJsonPath('discovered.0.match.status', 'suspected');

        $this->assertDatabaseCount('mice', 1);
        $this->assertDatabaseCount('dongles', 1);
        $this->assertDatabaseCount('device_matches', 1);
    }

    public function test_existing_active_match_is_reused_without_creating_a_duplicate(): void
    {
        $dongle = Dongle::factory()->create(['vid' => '046D', 'pid' => 'C52B']);
        $mouse = Mouse::factory()->create(['brand' => 'Logitech', 'model' => 'MX Master 3S']);
        $existingMatch = DeviceMatch::factory()->create([
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'matched',
            'matched_at' => now()->subDay(),
        ]);
        $this->mockScans([$this->usbReceiver()], [$this->usbReceiver(), $this->liveMouse()]);

        $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonPath('discovered.0.dongle.dongle_id', $dongle->id)
            ->assertJsonPath('discovered.0.dongle.auto_created', false)
            ->assertJsonPath('discovered.0.mouse.id', $mouse->id)
            ->assertJsonPath('discovered.0.mouse.auto_created', false)
            ->assertJsonPath('discovered.0.match.id', $existingMatch->id)
            ->assertJsonPath('discovered.0.match.status', 'matched');

        $this->assertDatabaseCount('device_matches', 1);
        $this->assertSame(
            $existingMatch->fresh()->matched_at->toIso8601String(),
            DeviceMatch::query()->firstOrFail()->matched_at->toIso8601String(),
        );
    }

    public function test_vendor_only_identity_is_flagged_for_review_and_never_auto_saved(): void
    {
        DB::table('usb_vendors')->insert([
            ['vid' => '7777', 'vendor_name' => 'Acme Peripherals', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $receiver = $this->usbReceiver([
            'name' => 'Wireless USB Receiver',
            'vid' => '7777',
            'pid' => '0001',
            'location' => 'Port_#0008',
            'device_instance_id' => 'USB\\VID_7777&PID_0001\\7&8',
        ]);
        $mouse = $this->liveMouse([
            'name' => 'HID-compliant mouse',
            'vid' => '7777',
            'pid' => '0001',
            'instance_id' => 'HID\\VID_7777&PID_0001&MI_00\\7&9',
        ]);
        $this->mockScans([$receiver], [$receiver, $mouse]);

        $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonCount(1, 'discovered')
            ->assertJsonPath('discovered.0.identity.brand', 'Acme Peripherals')
            ->assertJsonPath('discovered.0.identity.model', null)
            ->assertJsonPath('discovered.0.identity.source', 'vendor_only')
            ->assertJsonPath('discovered.0.identity.confidence', 'low')
            ->assertJsonPath('discovered.0.needs_user_review', true)
            ->assertJsonPath('discovered.0.mouse.auto_created', true)
            ->assertJsonPath('discovered.0.match.status', 'suspected');

        $this->assertDatabaseCount('mice', 1);
        $this->assertDatabaseCount('dongles', 1);
        $this->assertDatabaseCount('device_matches', 1);
    }

    public function test_usb_scanner_failure_returns_structured_error_without_writes(): void
    {
        $this->mockScans(
            [],
            [$this->usbReceiver(), $this->liveMouse()],
            'USB scanning is only available on Windows.',
        );

        $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonPath('error', 'USB scanning is only available on Windows.')
            ->assertJsonPath('message', 'Auto-discovery is unavailable: USB scanning is only available on Windows.')
            ->assertJsonPath('discovered', [])
            ->assertJsonPath('unattributed_devices', []);

        $this->assertDatabaseCount('mice', 0);
        $this->assertDatabaseCount('dongles', 0);
        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_live_detector_failure_returns_structured_error_without_writes(): void
    {
        $this->mockScans(
            [$this->usbReceiver()],
            [],
            null,
            'Live detection failed: Access denied.',
        );

        $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonPath('error', 'Live detection failed: Access denied.')
            ->assertJsonPath('discovered', [])
            ->assertJsonPath('unattributed_devices', []);

        $this->assertDatabaseCount('mice', 0);
        $this->assertDatabaseCount('dongles', 0);
        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_mouse_without_matching_dongle_is_reported_as_unattributed(): void
    {
        $receiver = $this->usbReceiver();
        $orphanMouse = $this->liveMouse([
            'name' => 'Unattributed Mouse',
            'vid' => '9999',
            'pid' => '0002',
            'instance_id' => 'HID\\VID_9999&PID_0002&MI_00\\7&11',
            'hardware_ids' => ['HID\\VID_9999&PID_0002&MI_00'],
        ]);
        $this->mockScans([$receiver], [$receiver, $orphanMouse]);

        $this->postJson('/api/auto-discover')
            ->assertOk()
            ->assertJsonPath('discovered', [])
            ->assertJsonCount(1, 'unattributed_devices')
            ->assertJsonPath('unattributed_devices.0.name', 'Unattributed Mouse')
            ->assertJsonPath('unattributed_devices.0.instance_id', $orphanMouse['instance_id'])
            ->assertJsonStructure(['unattributed_devices' => ['*' => [
                'name', 'vid', 'pid', 'instance_id', 'class', 'status', 'hardware_ids', 'location',
            ]]]);

        $this->assertDatabaseCount('mice', 0);
        $this->assertDatabaseCount('dongles', 0);
        $this->assertDatabaseCount('device_matches', 0);
    }

    /**
     * @param  array<int, array<string, mixed>>  $usbDevices
     * @param  array<int, array<string, mixed>>  $liveDevices
     */
    private function mockScans(
        array $usbDevices,
        array $liveDevices,
        ?string $usbError = null,
        ?string $liveError = null,
    ): void {
        $this->mock(UsbDeviceScanner::class)
            ->shouldReceive('scan')
            ->once()
            ->andReturn([
                'devices' => $usbDevices,
                'error' => $usbError,
                'scanned_at' => '2026-11-23T10:00:00+00:00',
            ]);

        $this->mock(LivePairingDetector::class)
            ->shouldReceive('scanOnly')
            ->once()
            ->andReturn([
                'devices' => $liveDevices,
                'error' => $liveError,
                'scanned_at' => '2026-11-23T10:00:00+00:00',
            ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function usbReceiver(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Logitech USB Receiver',
            'manufacturer' => 'Logitech',
            'description' => null,
            'vid' => '046D',
            'pid' => 'C52B',
            'hardware_ids' => ['USB\\VID_046D&PID_C52B&REV_0101'],
            'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            'serial_number' => null,
            'usb_class' => 'USB',
            'status' => 'OK',
            'location' => 'Port_#0001',
            'category' => 'other',
            'is_receiver' => true,
            'detected_at' => '2026-11-23T10:00:00+00:00',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function liveMouse(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Logitech MX Master 3S',
            'class' => 'Mouse',
            'status' => 'OK',
            'vid' => '046D',
            'pid' => 'C52B',
            'hardware_ids' => ['HID\\VID_046D&PID_C52B&MI_00'],
            'instance_id' => 'HID\\VID_046D&PID_C52B&MI_00\\7&9',
            'location' => 'Port_#0001',
        ], $overrides);
    }
}
