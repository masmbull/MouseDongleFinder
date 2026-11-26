<?php

namespace Tests\Feature;

use App\Models\Dongle;
use App\Models\Mouse;
use App\Services\LivePairingDetector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Live pairing tests use synthetic PnP payloads only: no real hardware and no
 * PowerShell process is ever started by these tests.
 */
class LivePairingTest extends TestCase
{
    use RefreshDatabase;

    public function test_live_match_endpoint_returns_the_documented_shape(): void
    {
        $this->mock(LivePairingDetector::class)
            ->shouldReceive('detect')
            ->once()
            ->andReturn($this->detectorResult());

        $this->postJson('/api/usb-scans/live-match')
            ->assertOk()
            ->assertJsonStructure([
                'scanned_at',
                'error',
                'dongles' => [
                    '*' => [
                        'vid', 'pid', 'name', 'dongle_id',
                        'connected_devices' => ['*' => [
                            'name', 'class', 'status', 'vid', 'pid',
                            'hardware_ids', 'instance_id', 'location',
                        ]],
                        'suggestions' => ['*' => [
                            'mouse_id', 'brand', 'model', 'confidence',
                            'reason', 'evidence' => ['*' => ['type', 'detail', 'source']],
                            'device_index',
                        ]],
                    ],
                ],
                'unattributed_devices' => ['*' => [
                    'name', 'class', 'status', 'vid', 'pid',
                    'hardware_ids', 'instance_id', 'location',
                ]],
                'message',
            ])
            ->assertJsonPath('error', null)
            ->assertJsonPath('dongles.0.vid', '046D')
            ->assertJsonPath('dongles.0.pid', 'C52B')
            ->assertJsonPath('dongles.0.dongle_id', 12)
            ->assertJsonPath('dongles.0.connected_devices.0.name', 'Logitech MX Master 3S')
            ->assertJsonPath('dongles.0.suggestions.0.mouse_id', 7)
            ->assertJsonPath('dongles.0.suggestions.0.confidence', 'high')
            ->assertJsonPath('dongles.0.suggestions.0.device_index', 0)
            ->assertJsonPath('message', 'Found 1 dongle candidate.');
    }

    public function test_live_match_endpoint_passes_through_structured_errors_with_empty_arrays(): void
    {
        $this->mock(LivePairingDetector::class)
            ->shouldReceive('detect')
            ->once()
            ->andReturn([
                'scanned_at' => '2026-11-21T10:00:00+00:00',
                'error' => 'USB scanning is only available on Windows.',
                'dongles' => [],
                'unattributed_devices' => [],
                'message' => 'Live detection is unavailable: USB scanning is only available on Windows.',
            ]);

        $this->postJson('/api/usb-scans/live-match')
            ->assertOk()
            ->assertJsonPath('error', 'USB scanning is only available on Windows.')
            ->assertJsonPath('dongles', [])
            ->assertJsonPath('unattributed_devices', []);
    }

    public function test_live_match_endpoint_returns_structured_error_when_detector_throws(): void
    {
        $this->mock(LivePairingDetector::class)
            ->shouldReceive('detect')
            ->once()
            ->andThrow(new RuntimeException('PowerShell exploded'));

        $this->postJson('/api/usb-scans/live-match')
            ->assertOk()
            ->assertJsonPath('dongles', [])
            ->assertJsonPath('unattributed_devices', [])
            ->assertJsonPath('error', 'Live detection failed: PowerShell exploded');
    }

    public function test_detector_returns_empty_result_on_windows_when_the_scan_fails(): void
    {
        $this->partialMock(LivePairingDetector::class, function ($mock): void {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('scanDevices')
                ->once()
                ->andReturn(['devices' => [], 'error' => 'Live detection failed: Access denied.']);
        });

        $result = app(LivePairingDetector::class)->detect();

        $this->assertSame('Live detection failed: Access denied.', $result['error']);
        $this->assertSame([], $result['dongles']);
        $this->assertSame([], $result['unattributed_devices']);
        $this->assertStringContainsString('unavailable', $result['message']);
    }

    public function test_detector_never_starts_a_process_on_a_non_windows_host(): void
    {
        $this->partialMock(LivePairingDetector::class, function ($mock): void {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('isWindowsHost')
                ->once()
                ->andReturn(false)
                ->shouldReceive('scanDevices')
                ->never();
        });

        $result = app(LivePairingDetector::class)->detect();

        $this->assertIsString($result['error']);
        $this->assertSame([], $result['dongles']);
        $this->assertSame([], $result['unattributed_devices']);
        $this->assertStringContainsString('only available on Windows', $result['error']);
    }

    public function test_detector_uses_topology_and_name_evidence_for_registered_mice(): void
    {
        $mouse = Mouse::factory()->create([
            'brand' => 'Logitech',
            'model' => 'MX Master 3S',
        ]);

        $this->partialMock(LivePairingDetector::class, function ($mock): void {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('scanDevices')
                ->once()
                ->andReturn(['devices' => $this->scanDevices(), 'error' => null]);
        });

        $result = app(LivePairingDetector::class)->detect();

        $this->assertNull($result['error']);
        $this->assertCount(1, $result['dongles']);
        $this->assertNull($result['dongles'][0]['dongle_id']);
        $this->assertCount(2, $result['dongles'][0]['connected_devices']);
        $this->assertSame([], $result['unattributed_devices']);

        $suggestion = $result['dongles'][0]['suggestions'][0];
        $this->assertSame($mouse->id, $suggestion['mouse_id']);
        $this->assertSame('high', $suggestion['confidence']);
        $this->assertSame(1, $suggestion['device_index']);
        $this->assertSame(
            'Logitech MX Master 3S',
            $result['dongles'][0]['connected_devices'][$suggestion['device_index']]['name'],
        );
        $this->assertContains('topology', array_column($suggestion['evidence'], 'type'));
        $this->assertContains('device_name', array_column($suggestion['evidence'], 'type'));
        $this->assertStringContainsString('physical pairing test', $suggestion['reason']);

        // The connected-device payload must not leak internal fields.
        $this->assertSame([
            'name', 'class', 'status', 'vid', 'pid', 'hardware_ids', 'instance_id', 'location',
        ], array_keys($result['dongles'][0]['connected_devices'][0]));
    }

    public function test_detector_creates_unregistered_pseudo_suggestion_without_saving_a_match(): void
    {
        $this->partialMock(LivePairingDetector::class, function ($mock): void {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('scanDevices')
                ->once()
                ->andReturn(['devices' => $this->scanDevices(), 'error' => null]);
        });

        $result = app(LivePairingDetector::class)->detect();
        $suggestion = $result['dongles'][0]['suggestions'][0];

        $this->assertNull($suggestion['mouse_id']);
        $this->assertSame('low', $suggestion['confidence']);
        $this->assertSame('Logitech MX Master 3S', $suggestion['model']);
        $this->assertContains('registration_required', array_column($suggestion['evidence'], 'type'));
        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_detector_uses_unknown_confidence_when_no_model_clue_exists(): void
    {
        $dongle = Dongle::factory()->create(['vid' => '9999', 'pid' => '0001']);

        $this->partialMock(LivePairingDetector::class, function ($mock): void {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('scanDevices')
                ->once()
                ->andReturn(['devices' => [[
                    'name' => 'Unknown Device',
                    'class' => 'Mouse',
                    'status' => 'OK',
                    'instance_id' => 'HID\\VID_9999&PID_0001\\7&9',
                    'vid' => '9999',
                    'pid' => '0001',
                    'hardware_ids' => ['HID\\VID_9999&PID_0001'],
                    'location' => '',
                    'is_input' => true,
                ]], 'error' => null]);
        });

        $result = app(LivePairingDetector::class)->detect();
        $suggestion = $result['dongles'][0]['suggestions'][0];

        $this->assertSame($dongle->id, $result['dongles'][0]['dongle_id']);
        $this->assertNull($suggestion['mouse_id']);
        $this->assertSame('unknown', $suggestion['confidence']);
    }

    public function test_detector_caps_normalized_devices_at_one_hundred(): void
    {
        $devices = [];

        for ($index = 0; $index < 140; $index++) {
            $devices[] = [
                'name' => 'Unrelated device '.$index,
                'class' => 'USB',
                'status' => 'OK',
                'instance_id' => 'USB\\VID_9999&PID_9999\\'.$index,
                'vid' => '9999',
                'pid' => '9999',
                'hardware_ids' => [],
                'location' => '',
                'is_input' => false,
            ];
        }

        $this->partialMock(LivePairingDetector::class, function ($mock) use ($devices): void {
            $mock->shouldAllowMockingProtectedMethods()
                ->shouldReceive('scanDevices')
                ->once()
                ->andReturn(['devices' => $devices, 'error' => null]);
        });

        $result = app(LivePairingDetector::class)->detect();

        $this->assertCount(100, $result['unattributed_devices']);
        $this->assertSame([], $result['dongles']);
    }

    /**
     * @return array<string, mixed>
     */
    private function detectorResult(): array
    {
        return [
            'scanned_at' => '2026-11-21T10:00:00+00:00',
            'error' => null,
            'dongles' => [[
                'vid' => '046D',
                'pid' => 'C52B',
                'name' => 'Logitech Unifying Receiver',
                'dongle_id' => 12,
                'connected_devices' => [[
                    'name' => 'Logitech MX Master 3S',
                    'class' => 'Mouse',
                    'status' => 'OK',
                    'vid' => '046D',
                    'pid' => 'C52B',
                    'hardware_ids' => ['HID\\VID_046D&PID_C52B&MI_00'],
                    'instance_id' => 'HID\\VID_046D&PID_C52B&MI_00\\7&2',
                    'location' => 'Port_#0001',
                ]],
                'suggestions' => [[
                    'mouse_id' => 7,
                    'brand' => 'Logitech',
                    'model' => 'MX Master 3S',
                    'confidence' => 'high',
                    'reason' => 'Topology and name match. Confirm with a physical pairing test.',
                    'evidence' => [[
                        'type' => 'topology',
                        'detail' => 'Shared VID/PID.',
                        'source' => 'windows_pnp',
                    ]],
                    'device_index' => 0,
                ]],
            ]],
            'unattributed_devices' => [],
            'message' => 'Found 1 dongle candidate.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scanDevices(): array
    {
        return [
            [
                'name' => 'Logitech USB Receiver',
                'class' => 'USB',
                'status' => 'OK',
                'instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
                'vid' => '046D',
                'pid' => 'C52B',
                'hardware_ids' => ['USB\\VID_046D&PID_C52B&REV_0101'],
                'location' => 'Port_#0001',
                'is_input' => false,
                'detected_at' => '2026-11-21T10:00:00+00:00',
            ],
            [
                'name' => 'Logitech MX Master 3S',
                'class' => 'Mouse',
                'status' => 'OK',
                'instance_id' => 'HID\\VID_046D&PID_C52B&MI_00\\7&2',
                'vid' => '046D',
                'pid' => 'C52B',
                'hardware_ids' => ['HID\\VID_046D&PID_C52B&MI_00'],
                'location' => 'Port_#0001',
                'is_input' => true,
                'detected_at' => '2026-11-21T10:00:00+00:00',
            ],
        ];
    }
}
