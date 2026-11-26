<?php

namespace Tests\Feature;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\MatchingSession;
use App\Models\Mouse;
use App\Models\MouseProfile;
use App\Services\DongleLabService;
use App\Services\LivePairingDetector;
use App\Services\UsbDeviceScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Dongle Lab coverage. All USB and HID payloads are synthetic; the hardware
 * scanners are always mocked and no match is ever auto-saved.
 */
class MatchingSessionTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_labels_receiver_candidates_with_brand_family_and_port(): void
    {
        DB::table('usb_vendors')->insert([
            ['vid' => '258A', 'vendor_name' => 'Keychron', 'created_at' => now(), 'updated_at' => now()],
        ]);
        DB::table('usb_products')->insert([
            ['vid' => '046D', 'pid' => 'C52B', 'product_name' => 'Unifying Receiver', 'created_at' => now(), 'updated_at' => now()],
            ['vid' => '1532', 'pid' => '007B', 'product_name' => 'HyperSpeed Receiver', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001 Hub_#1',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
                'hardware_ids' => ['USB\\VID_046D&PID_C52B&REV_0101'],
            ]),
            $this->usbDevice([
                'name' => 'Razer HyperSpeed Dongle',
                'vid' => '1532',
                'pid' => '007B',
                'location' => 'Port_#0002 Hub_#1',
                'device_instance_id' => 'USB\\VID_1532&PID_007B\\7&2',
                'hardware_ids' => ['USB\\VID_1532&PID_007B&REV_0100'],
            ]),
            $this->usbDevice([
                'name' => 'Wireless USB Receiver',
                'vid' => '258A',
                'pid' => '1234',
                'location' => 'Port_#0003 Hub_#1',
                'device_instance_id' => 'USB\\VID_258A&PID_1234\\7&3',
                'hardware_ids' => ['USB\\VID_258A&PID_1234&REV_0100'],
            ]),
        ]);

        $this->postJson('/api/matching-sessions')
            ->assertCreated()
            ->assertJsonStructure([
                'session' => ['id', 'name', 'status', 'dongles', 'observations', 'started_at'],
                'dongles' => ['*' => [
                    'key', 'vid', 'pid', 'name', 'brand_guess',
                    'receiver_family', 'location', 'port', 'dongle_id',
                    'confidence', 'note',
                ]],
                'message',
            ])
            ->assertJsonPath('dongles.0.key', 'A')
            ->assertJsonPath('dongles.0.brand_guess', 'Logitech')
            ->assertJsonPath('dongles.0.receiver_family', 'Unifying')
            ->assertJsonPath('dongles.0.location', 'Port_#0001 Hub_#1')
            ->assertJsonPath('dongles.0.port', 'Port_#0001')
            ->assertJsonPath('dongles.0.confidence', 'high')
            ->assertJsonPath('dongles.1.key', 'B')
            ->assertJsonPath('dongles.1.brand_guess', 'Razer')
            ->assertJsonPath('dongles.1.receiver_family', 'HyperSpeed')
            ->assertJsonPath('dongles.1.port', 'Port_#0002')
            ->assertJsonPath('dongles.1.confidence', 'high')
            ->assertJsonPath('dongles.2.key', 'C')
            ->assertJsonPath('dongles.2.brand_guess', 'Keychron')
            ->assertJsonPath('dongles.2.port', 'Port_#0003')
            ->assertJsonPath('dongles.2.confidence', 'medium');

        $session = MatchingSession::query()->firstOrFail();

        $this->assertSame('scanning', $session->status);
        $this->assertCount(3, $session->dongles);
        $this->assertCount(1, $session->observations);
        $this->assertCount(3, $session->observations[0]['devices']);
        $this->assertDatabaseCount('matching_sessions', 1);
        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_duplicate_vid_pid_dongles_get_distinct_labels(): void
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
            $this->usbDevice([
                'name' => 'HID-compliant mouse',
                'vid' => '046D',
                'pid' => 'C52B',
                'usb_class' => 'HIDClass',
                'location' => 'Port_#0001',
                'device_instance_id' => 'HID\\VID_046D&PID_C52B&MI_00\\7&2',
            ]),
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0002',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&3',
            ]),
        ]);

        $this->postJson('/api/matching-sessions')
            ->assertCreated()
            ->assertJsonCount(2, 'dongles')
            ->assertJsonPath('dongles.0.key', 'A')
            ->assertJsonPath('dongles.0.port', 'Port_#0001')
            ->assertJsonPath('dongles.1.key', 'B')
            ->assertJsonPath('dongles.1.port', 'Port_#0002');
    }

    public function test_scan_excludes_intel_bluetooth_and_generic_non_receivers(): void
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Intel(R) Wireless Bluetooth',
                'vid' => '8087',
                'pid' => '0026',
                'device_instance_id' => 'USB\\VID_8087&PID_0026\\7&1',
            ]),
            $this->usbDevice([
                'name' => 'Bluetooth USB Adapter',
                'vid' => '0B05',
                'pid' => '17CB',
                'device_instance_id' => 'USB\\VID_0B05&PID_17CB\\7&2',
            ]),
            $this->usbDevice([
                'name' => 'HID-compliant mouse',
                'vid' => '9999',
                'pid' => '0001',
                'usb_class' => 'HIDClass',
                'device_instance_id' => 'HID\\VID_9999&PID_0001\\7&3',
                'location' => 'Port_#0004',
            ]),
        ]);

        $this->postJson('/api/matching-sessions')
            ->assertCreated()
            ->assertJsonPath('dongles', [])
            ->assertJsonPath('session.dongles', []);
    }

    public function test_registered_dongle_is_attached_and_mouse_profile_supplies_receiver_family(): void
    {
        $dongle = Dongle::factory()->create([
            'vid' => '9999',
            'pid' => '0001',
            'brand' => 'Registered Brand',
        ]);
        MouseProfile::factory()->create([
            'brand' => 'Probe Brand',
            'model' => 'Probe Receiver',
            'vid' => '9999',
            'pid' => '0001',
            'receiver_family' => 'Custom Family',
        ]);

        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'USB Receiver',
                'vid' => '9999',
                'pid' => '0001',
                'location' => 'Port_#0005',
                'device_instance_id' => 'USB\\VID_9999&PID_0001\\7&1',
            ]),
        ]);

        $this->postJson('/api/matching-sessions')
            ->assertCreated()
            ->assertJsonPath('dongles.0.dongle_id', $dongle->id)
            ->assertJsonPath('dongles.0.brand_guess', 'Probe Brand')
            ->assertJsonPath('dongles.0.receiver_family', 'Custom Family')
            ->assertJsonPath('dongles.0.confidence', 'high')
            ->assertJsonPath('dongles.0.note', 'Registered dongle '.$dongle->dongle_id.'; name matches a receiver pattern; VID:PID documented in local reference data.');
    }

    public function test_observe_returns_only_new_devices_and_persists_a_snapshot(): void
    {
        $baselineUsb = $this->usbDevice([
            'name' => 'Logitech USB Receiver',
            'vid' => '046D',
            'pid' => 'C52B',
            'location' => 'Port_#0001',
            'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
        ]);
        $baselineHub = $this->usbDevice([
            'name' => 'USB Hub',
            'vid' => '05E3',
            'pid' => '0610',
            'location' => 'Port_#0002',
            'device_instance_id' => 'USB\\VID_05E3&PID_0610\\7&2',
        ]);

        $this->mockScannerDevices([$baselineUsb, $baselineHub]);

        $store = $this->postJson('/api/matching-sessions')->assertCreated();
        $sessionId = $store->json('session.id');

        $newMouse = $this->liveDevice([
            'name' => 'Unregistered Mouse',
            'class' => 'HIDClass',
            'instance_id' => 'HID\\VID_9999&PID_0002\\7&9',
        ]);

        $this->mockDetectorDevices([
            $this->liveDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
            $this->liveDevice([
                'name' => 'USB Hub',
                'class' => 'USB',
                'vid' => '05E3',
                'pid' => '0610',
                'instance_id' => 'USB\\VID_05E3&PID_0610\\7&2',
            ]),
            $newMouse,
        ]);

        $this->postJson("/api/matching-sessions/{$sessionId}/observe")
            ->assertOk()
            ->assertJsonCount(1, 'new_devices')
            ->assertJsonPath('new_devices.0.instance_id', $newMouse['instance_id'])
            ->assertJsonPath('observation_count', 2)
            ->assertJsonStructure(['observed_at', 'new_devices', 'candidates', 'message', 'observation_count']);

        $session = MatchingSession::query()->findOrFail($sessionId);

        $this->assertSame('observing', $session->status);
        $this->assertCount(2, $session->observations);
        $this->assertCount(3, $session->observations[1]['devices']);
        $this->assertCount(1, $session->observations[1]['candidates']);
        $this->assertSame([], $session->observations[1]['candidates'][0]['candidates']);
    }

    public function test_observe_attributes_new_mouse_to_every_matching_dongle(): void
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0002',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&3',
            ]),
        ]);

        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->mockDetectorDevices([
            $this->liveDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
            $this->liveDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'instance_id' => 'USB\\VID_046D&PID_C52B\\7&3',
            ]),
            $this->liveDevice([
                'name' => 'Logitech MX Master 3S',
                'class' => 'Mouse',
                'vid' => '046D',
                'pid' => 'C52B',
                'instance_id' => 'HID\\VID_046D&PID_C52B&MI_00\\7&9',
                'hardware_ids' => ['HID\\VID_046D&PID_C52B&MI_00'],
            ]),
        ]);

        $this->postJson("/api/matching-sessions/{$sessionId}/observe")
            ->assertOk()
            ->assertJsonCount(1, 'candidates')
            ->assertJsonCount(2, 'candidates.0.candidates')
            ->assertJsonPath('candidates.0.candidates.0.dongle_key', 'A')
            ->assertJsonPath('candidates.0.candidates.0.confidence', 'high')
            ->assertJsonPath('candidates.0.candidates.1.dongle_key', 'B')
            ->assertJsonPath('candidates.0.candidates.1.confidence', 'high')
            ->assertJsonPath(
                'candidates.0.message',
                'Multiple dongles match these identifiers. Choose the correct one by USB port/location and confirm with a physical pairing test.',
            )
            ->assertJsonStructure([
                'candidates' => ['*' => [
                    'device', 'device_instance_id', 'candidates' => ['*' => [
                        'dongle_key', 'dongle_id', 'reason', 'confidence',
                        'evidence' => ['*' => ['type', 'detail', 'source']],
                    ]], 'message',
                ]],
            ]);

        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_observe_attributes_via_hardware_ids_when_device_ids_are_absent(): void
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech Unifying Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
        ]);

        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->mockDetectorDevices([
            $this->liveDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
            $this->liveDevice([
                'name' => 'Wireless Mouse',
                'class' => 'Mouse',
                'vid' => null,
                'pid' => null,
                'instance_id' => 'HID\\VID_046D&PID_C52B&MI_00\\7&8',
                'hardware_ids' => ['HID\\VID_046D&PID_C52B&MI_00'],
            ]),
        ]);

        $this->postJson("/api/matching-sessions/{$sessionId}/observe")
            ->assertOk()
            ->assertJsonCount(1, 'candidates.0.candidates')
            ->assertJsonPath('candidates.0.candidates.0.dongle_key', 'A')
            ->assertJsonPath('candidates.0.candidates.0.confidence', 'medium')
            ->assertJsonPath('candidates.0.candidates.0.evidence.0.type', 'hardware_id');
    }

    public function test_observe_reports_unattributed_device_when_no_topology_matches(): void
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
        ]);

        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->mockDetectorDevices([
            $this->liveDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
            $this->liveDevice([
                'name' => 'Mystery Mouse',
                'class' => 'Mouse',
                'vid' => '9999',
                'pid' => '0002',
                'instance_id' => 'HID\\VID_9999&PID_0002\\7&9',
                'hardware_ids' => ['HID\\VID_9999&PID_0002'],
            ]),
        ]);

        $this->postJson("/api/matching-sessions/{$sessionId}/observe")
            ->assertOk()
            ->assertJsonCount(0, 'candidates.0.candidates')
            ->assertJsonPath(
                'candidates.0.message',
                'This device could not be attributed to any detected dongle by VID/PID or hardware IDs.',
            );
    }

    public function test_observe_persists_history_with_mouse_id_and_notes(): void
    {
        $mouse = Mouse::factory()->create(['brand' => 'Logitech', 'model' => 'MX Master 3S']);

        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
        ]);

        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');
        $newMouse = $this->liveDevice([
            'name' => 'Logitech MX Master 3S',
            'class' => 'Mouse',
            'vid' => '046D',
            'pid' => 'C52B',
            'instance_id' => 'HID\\VID_046D&PID_C52B&MI_00\\7&9',
        ]);

        $this->mockDetectorDevices(
            [
                $this->liveDevice([
                    'name' => 'Logitech USB Receiver',
                    'vid' => '046D',
                    'pid' => 'C52B',
                    'instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
                ]),
                $newMouse,
            ],
            null,
            2,
        );

        $this->postJson("/api/matching-sessions/{$sessionId}/observe", [
            'mouse_id' => $mouse->id,
            'notes' => 'Switched on the test mouse',
        ])->assertOk()
            ->assertJsonPath('observation_count', 2)
            ->assertJsonPath('candidates.0.device_instance_id', $newMouse['instance_id']);

        $this->postJson("/api/matching-sessions/{$sessionId}/observe")
            ->assertOk()
            ->assertJsonPath('observation_count', 3)
            ->assertJsonCount(0, 'new_devices');

        $session = MatchingSession::query()->findOrFail($sessionId);

        $this->assertCount(3, $session->observations);
        $this->assertSame($mouse->id, $session->observations[1]['mouse_id']);
        $this->assertSame('Switched on the test mouse', $session->observations[1]['notes']);
        $this->assertSame([], $session->observations[2]['new_devices']);
        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_observe_validates_that_mouse_id_exists(): void
    {
        $this->mockScannerDevices([]);
        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->postJson("/api/matching-sessions/{$sessionId}/observe", ['mouse_id' => 99999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('mouse_id');

        $session = MatchingSession::query()->findOrFail($sessionId);

        $this->assertCount(1, $session->observations);
        $this->assertSame('scanning', $session->status);
    }

    public function test_observe_with_dongle_key_attributes_an_already_present_mouse(): void
    {
        $sessionId = $this->sessionWithAlreadyPresentMouse();
        $this->mockAlreadyPresentMouseAndDongle();

        $this->postJson("/api/matching-sessions/{$sessionId}/observe", ['dongle_key' => 'A'])
            ->assertOk()
            ->assertJsonPath('tested_dongle_key', 'A')
            ->assertJsonCount(0, 'new_devices')
            ->assertJsonCount(1, 'connected_devices')
            ->assertJsonPath('connected_devices.0.instance_id', 'HID\\VID_8888&PID_0002&MI_00\\7&9')
            ->assertJsonPath('connected_devices.0.is_new', false)
            ->assertJsonCount(1, 'candidates')
            ->assertJsonCount(1, 'candidates.0.candidates')
            ->assertJsonPath('candidates.0.candidates.0.dongle_key', 'A')
            ->assertJsonPath('candidates.0.candidates.0.vid', '7777')
            ->assertJsonPath('candidates.0.candidates.0.pid', '0001')
            ->assertJsonPath('candidates.0.candidates.0.confidence', 'high')
            ->assertJsonPath('candidates.0.candidates.0.evidence.0.type', 'user_test')
            ->assertJsonPath('candidates.0.candidates.0.evidence.0.source', 'user_declared')
            ->assertJsonStructure([
                'connected_devices' => ['*' => [
                    'name', 'class', 'status', 'vid', 'pid', 'hardware_ids', 'instance_id', 'location', 'is_new',
                ]],
                'candidates' => ['*' => ['candidates' => ['*' => [
                    'dongle_key', 'dongle_id', 'vid', 'pid', 'reason', 'evidence', 'confidence',
                ]]]],
            ]);

        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_observe_without_dongle_key_reports_no_candidate_for_unmatched_present_mouse(): void
    {
        $sessionId = $this->sessionWithAlreadyPresentMouse();
        $this->mockAlreadyPresentMouseAndDongle();

        $this->postJson("/api/matching-sessions/{$sessionId}/observe")
            ->assertOk()
            ->assertJsonPath('tested_dongle_key', null)
            ->assertJsonCount(0, 'new_devices')
            ->assertJsonCount(1, 'connected_devices')
            ->assertJsonCount(0, 'candidates.0.candidates')
            ->assertJsonPath(
                'candidates.0.message',
                'This device could not be attributed to any detected dongle by VID/PID or hardware IDs.',
            )
            ->assertJsonPath(
                'message',
                '1 mouse-like device(s) are connected, but none matched a dongle by VID/PID or hardware IDs. Select the labelled dongle you are testing and observe again.',
            );
    }

    public function test_observe_returns_422_for_dongle_key_outside_the_session(): void
    {
        $sessionId = $this->sessionWithAlreadyPresentMouse();

        $this->postJson("/api/matching-sessions/{$sessionId}/observe", ['dongle_key' => 'Z'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dongle_key');

        $session = MatchingSession::query()->findOrFail($sessionId);

        $this->assertCount(1, $session->observations);
        $this->assertSame('scanning', $session->status);
    }

    public function test_observe_user_test_candidate_is_medium_with_multiple_dongles(): void
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
            $this->usbDevice([
                'name' => 'Razer HyperSpeed Dongle',
                'vid' => '1532',
                'pid' => '007B',
                'location' => 'Port_#0002',
                'device_instance_id' => 'USB\\VID_1532&PID_007B\\7&2',
            ]),
            $this->usbDevice([
                'name' => 'Mystery Mouse',
                'usb_class' => 'Mouse',
                'vid' => '8888',
                'pid' => '0002',
                'device_instance_id' => 'HID\\VID_8888&PID_0002&MI_00\\7&9',
            ]),
        ]);
        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->mockDetectorDevices([
            $this->liveDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
            $this->liveDevice([
                'name' => 'Razer HyperSpeed Dongle',
                'vid' => '1532',
                'pid' => '007B',
                'instance_id' => 'USB\\VID_1532&PID_007B\\7&2',
            ]),
            $this->liveDevice([
                'name' => 'Mystery Mouse',
                'class' => 'Mouse',
                'vid' => '8888',
                'pid' => '0002',
                'instance_id' => 'HID\\VID_8888&PID_0002&MI_00\\7&9',
            ]),
        ]);

        $this->postJson("/api/matching-sessions/{$sessionId}/observe", ['dongle_key' => 'B'])
            ->assertOk()
            ->assertJsonPath('tested_dongle_key', 'B')
            ->assertJsonCount(1, 'candidates.0.candidates')
            ->assertJsonPath('candidates.0.candidates.0.dongle_key', 'B')
            ->assertJsonPath('candidates.0.candidates.0.vid', '1532')
            ->assertJsonPath('candidates.0.candidates.0.pid', '007B')
            ->assertJsonPath('candidates.0.candidates.0.confidence', 'medium')
            ->assertJsonPath('candidates.0.candidates.0.evidence.0.type', 'user_test');
    }

    public function test_observe_reports_all_connected_mice_with_is_new_flags(): void
    {
        $existingMouse = $this->usbDevice([
            'name' => 'Existing Mouse',
            'usb_class' => 'Mouse',
            'vid' => '8888',
            'pid' => '0001',
            'device_instance_id' => 'HID\\VID_8888&PID_0001\\7&8',
        ]);
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Wireless USB Receiver',
                'vid' => '7777',
                'pid' => '0001',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_7777&PID_0001\\7&1',
            ]),
            $existingMouse,
        ]);
        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $newMouse = $this->liveDevice([
            'name' => 'New Mouse',
            'class' => 'Mouse',
            'vid' => '8888',
            'pid' => '0002',
            'instance_id' => 'HID\\VID_8888&PID_0002&MI_00\\7&9',
        ]);
        $this->mockDetectorDevices([
            $this->liveDevice([
                'name' => 'Wireless USB Receiver',
                'vid' => '7777',
                'pid' => '0001',
                'instance_id' => 'USB\\VID_7777&PID_0001\\7&1',
            ]),
            $this->liveDevice([
                'name' => 'Existing Mouse',
                'class' => 'Mouse',
                'vid' => '8888',
                'pid' => '0001',
                'instance_id' => 'HID\\VID_8888&PID_0001\\7&8',
            ]),
            $newMouse,
        ]);

        $this->postJson("/api/matching-sessions/{$sessionId}/observe", ['dongle_key' => 'A'])
            ->assertOk()
            ->assertJsonCount(2, 'connected_devices')
            ->assertJsonPath('connected_devices.0.name', 'Existing Mouse')
            ->assertJsonPath('connected_devices.0.is_new', false)
            ->assertJsonPath('connected_devices.1.name', 'New Mouse')
            ->assertJsonPath('connected_devices.1.is_new', true)
            ->assertJsonCount(1, 'new_devices')
            ->assertJsonPath('new_devices.0.instance_id', $newMouse['instance_id']);
    }

    public function test_scan_dongles_falls_back_to_local_usb_ids_file(): void
    {
        $usbIdsPath = sys_get_temp_dir().'/mdf-usbids-'.bin2hex(random_bytes(6)).'.ids';
        file_put_contents($usbIdsPath, implode("\n", [
            '# Local test data',
            'ABCD  Acme Devices',
            "\t1234  Acme Wireless Receiver",
            '7777  No Product Vendor',
            '9998  Unknown Vendor',
            'C 00  Device class section',
            "\t1234  Should Not Be Mapped To The Previous Vendor",
        ]));

        try {
            $this->mockScannerDevices([
                $this->usbDevice([
                    'name' => 'Wireless USB Receiver',
                    'vid' => 'ABCD',
                    'pid' => '1234',
                    'location' => 'Port_#0001',
                    'device_instance_id' => 'USB\\VID_ABCD&PID_1234\\7&1',
                ]),
                $this->usbDevice([
                    'name' => 'Wireless USB Receiver',
                    'vid' => 'ABCD',
                    'pid' => '9999',
                    'location' => 'Port_#0002',
                    'device_instance_id' => 'USB\\VID_ABCD&PID_9999\\7&2',
                ]),
                $this->usbDevice([
                    'name' => 'Wireless USB Receiver',
                    'vid' => '7777',
                    'pid' => '0001',
                    'location' => 'Port_#0003',
                    'device_instance_id' => 'USB\\VID_7777&PID_0001\\7&3',
                ]),
                $this->usbDevice([
                    'name' => 'Wireless USB Receiver',
                    'vid' => '9998',
                    'pid' => '0001',
                    'location' => 'Port_#0004',
                    'device_instance_id' => 'USB\\VID_9998&PID_0001\\7&4',
                ]),
                $this->usbDevice([
                    'name' => 'Wireless USB Receiver',
                    'vid' => '9997',
                    'pid' => '0001',
                    'location' => 'Port_#0005',
                    'device_instance_id' => 'USB\\VID_9997&PID_0001\\7&5',
                ]),
            ]);
            $service = new DongleLabService(
                $this->app->make(UsbDeviceScanner::class),
                $this->app->make(LivePairingDetector::class),
                $usbIdsPath,
            );
            $this->app->instance(DongleLabService::class, $service);

            $this->postJson('/api/matching-sessions')
                ->assertCreated()
                ->assertJsonPath('dongles.0.brand_guess', 'Acme Devices')
                ->assertJsonPath('dongles.0.confidence', 'high')
                ->assertJsonPath('dongles.1.brand_guess', 'Acme Devices')
                ->assertJsonPath('dongles.1.confidence', 'medium')
                ->assertJsonPath('dongles.2.brand_guess', 'No Product Vendor')
                ->assertJsonPath('dongles.2.confidence', 'medium')
                ->assertJsonPath('dongles.3.brand_guess', 'Unknown Vendor')
                ->assertJsonPath('dongles.3.confidence', 'medium')
                ->assertJsonPath('dongles.4.brand_guess', 'Unknown')
                ->assertJsonPath('dongles.4.confidence', 'low');
        } finally {
            @unlink($usbIdsPath);
        }
    }

    public function test_label_updates_dongle_and_marks_the_label_as_manual(): void
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Wireless USB Receiver',
                'vid' => '258A',
                'pid' => '1234',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_258A&PID_1234\\7&1',
            ]),
        ]);
        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->postJson("/api/matching-sessions/{$sessionId}/label", [
            'dongle_key' => 'A',
            'brand' => 'Mikuso',
            'model' => 'Mikuso Wireless Receiver',
            'notes' => 'Label read from the sticker',
        ])
            ->assertOk()
            ->assertJsonPath('dongles.0.brand_guess', 'Mikuso')
            ->assertJsonPath('dongles.0.brand_source', 'manual')
            ->assertJsonPath('dongles.0.manual_label', true)
            ->assertJsonPath('dongles.0.name', 'Mikuso Wireless Receiver')
            ->assertJsonPath('session.dongles.0.brand_guess', 'Mikuso')
            ->assertJsonPath(
                'message',
                'Dongle A was labelled manually. Confirm the relationship with a physical pairing test before saving a match.',
            );

        $note = MatchingSession::query()->findOrFail($sessionId)->dongles[0]['note'];

        $this->assertStringContainsString('Label read from the sticker', $note);
        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_label_returns_422_for_a_dongle_key_outside_the_session(): void
    {
        $this->mockScannerDevices([]);
        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->postJson("/api/matching-sessions/{$sessionId}/label", [
            'dongle_key' => 'Z',
            'brand' => 'Mikuso',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('dongle_key');

        $this->assertSame([], MatchingSession::query()->findOrFail($sessionId)->dongles);
    }

    public function test_post_alias_route_observes_a_session(): void
    {
        $this->mockScannerDevices([]);
        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->mockDetectorDevices([]);

        $this->postJson("/api/matching-sessions/{$sessionId}")
            ->assertOk()
            ->assertJsonStructure(['observed_at', 'new_devices', 'candidates', 'message', 'observation_count'])
            ->assertJsonPath('observation_count', 2);
    }

    public function test_non_windows_scan_returns_a_structured_error_without_devices(): void
    {
        $this->mockScannerDevices([], 'USB scanning is only available on Windows.');

        $this->postJson('/api/matching-sessions')
            ->assertCreated()
            ->assertJsonPath('error', 'USB scanning is only available on Windows.')
            ->assertJsonPath('dongles', [])
            ->assertJsonPath('session.dongles', [])
            ->assertJsonPath('session.observations.0.devices', []);

        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_observe_returns_a_structured_error_when_live_detection_fails(): void
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
        ]);
        $sessionId = $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');

        $this->mockDetectorDevices([], 'Live detection failed: Access denied.');

        $this->postJson("/api/matching-sessions/{$sessionId}/observe")
            ->assertOk()
            ->assertJsonPath('error', 'Live detection failed: Access denied.')
            ->assertJsonPath('new_devices', [])
            ->assertJsonPath('candidates', [])
            ->assertJsonPath('observation_count', 1);

        $session = MatchingSession::query()->findOrFail($sessionId);

        $this->assertCount(1, $session->observations);
        $this->assertSame('scanning', $session->status);
    }

    public function test_complete_and_destroy_a_session(): void
    {
        $session = MatchingSession::factory()->create();

        $this->postJson("/api/matching-sessions/{$session->id}/complete")
            ->assertOk()
            ->assertJsonPath('id', $session->id)
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('completed_at', $session->fresh()->completed_at?->toJSON());

        $this->assertNotNull($session->fresh()->completed_at);

        $this->deleteJson("/api/matching-sessions/{$session->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Matching session deleted successfully');

        $this->assertModelMissing($session);
    }

    public function test_index_and_show_return_session_details(): void
    {
        $newest = MatchingSession::factory()->create(['name' => 'Newest session']);
        $older = MatchingSession::factory()->create([
            'name' => 'Older session',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $this->getJson('/api/matching-sessions')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total', 'from', 'to']);

        $this->getJson("/api/matching-sessions/{$newest->id}")
            ->assertOk()
            ->assertJsonPath('id', $newest->id)
            ->assertJsonPath('name', 'Newest session')
            ->assertJsonPath('status', 'scanning');

        $this->getJson("/api/matching-sessions/{$older->id}")
            ->assertOk()
            ->assertJsonPath('id', $older->id);
    }

    private function sessionWithAlreadyPresentMouse(): int
    {
        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Wireless USB Receiver',
                'vid' => '7777',
                'pid' => '0001',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_7777&PID_0001\\7&1',
            ]),
            $this->usbDevice([
                'name' => 'Mystery Mouse',
                'usb_class' => 'Mouse',
                'vid' => '8888',
                'pid' => '0002',
                'location' => 'Port_#0001',
                'device_instance_id' => 'HID\\VID_8888&PID_0002&MI_00\\7&9',
            ]),
        ]);

        return (int) $this->postJson('/api/matching-sessions')->assertCreated()->json('session.id');
    }

    private function mockAlreadyPresentMouseAndDongle(): void
    {
        $this->mockDetectorDevices([
            $this->liveDevice([
                'name' => 'Wireless USB Receiver',
                'vid' => '7777',
                'pid' => '0001',
                'instance_id' => 'USB\\VID_7777&PID_0001\\7&1',
            ]),
            $this->liveDevice([
                'name' => 'Mystery Mouse',
                'class' => 'Mouse',
                'vid' => '8888',
                'pid' => '0002',
                'instance_id' => 'HID\\VID_8888&PID_0002&MI_00\\7&9',
            ]),
        ]);
    }

    public function test_scan_includes_recorded_matched_mice_for_detected_dongles(): void
    {
        $dongle = Dongle::factory()->create(['vid' => '046D', 'pid' => 'C52B']);
        $mouse = Mouse::factory()->create(['brand' => 'Logitech', 'model' => 'M185']);
        DeviceMatch::factory()->create([
            'dongle_id' => $dongle->id,
            'mouse_id' => $mouse->id,
            'status' => 'matched',
            'notes' => 'Physically tested.',
        ]);

        $this->mockScannerDevices([
            $this->usbDevice([
                'name' => 'Logitech USB Receiver',
                'vid' => '046D',
                'pid' => 'C52B',
                'location' => 'Port_#0001',
                'device_instance_id' => 'USB\\VID_046D&PID_C52B\\7&1',
            ]),
        ]);

        $response = $this->postJson('/api/matching-sessions')->assertCreated();
        $response->assertJsonPath('dongles.0.matched_mice.0.mouse_code', $mouse->mouse_id)
            ->assertJsonPath('dongles.0.matched_mice.0.status', 'matched')
            ->assertJsonPath('dongles.0.matched_mice.0.match_source', 'registered');
    }

    /**
     * @param  array<int, array<string, mixed>>  $devices
     */
    private function mockScannerDevices(array $devices, ?string $error = null, int $times = 1): void
    {
        $this->mock(UsbDeviceScanner::class)
            ->shouldReceive('scan')
            ->times($times)
            ->andReturn([
                'devices' => $devices,
                'error' => $error,
                'scanned_at' => '2026-11-21T10:00:00+00:00',
            ]);

        $liveDevices = array_map(function (array $device): array {
            return [
                'name' => $device['name'] ?? 'Unknown Device',
                'class' => $device['usb_class'] ?? $device['class'] ?? '',
                'status' => $device['status'] ?? 'OK',
                'vid' => $device['vid'] ?? null,
                'pid' => $device['pid'] ?? null,
                'hardware_ids' => $device['hardware_ids'] ?? [],
                'instance_id' => $device['device_instance_id'] ?? $device['instance_id'] ?? '',
                'location' => $device['location'] ?? '',
            ];
        }, $devices);

        $this->mock(LivePairingDetector::class)
            ->shouldReceive('scanOnly')
            ->times($times)
            ->andReturn([
                'devices' => $liveDevices,
                'error' => $error,
                'scanned_at' => '2026-11-21T10:00:00+00:00',
            ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $devices
     */
    private function mockDetectorDevices(array $devices, ?string $error = null, int $times = 1): void
    {
        $this->mock(LivePairingDetector::class)
            ->shouldReceive('scanOnly')
            ->times($times)
            ->andReturn([
                'devices' => $devices,
                'error' => $error,
                'scanned_at' => '2026-11-21T10:00:00+00:00',
            ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function usbDevice(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Unknown Device',
            'manufacturer' => null,
            'description' => null,
            'vid' => null,
            'pid' => null,
            'hardware_ids' => [],
            'device_instance_id' => '',
            'serial_number' => null,
            'usb_class' => 'USB',
            'driver_name' => null,
            'driver_version' => null,
            'driver_provider' => null,
            'status' => 'OK',
            'location' => null,
            'category' => 'other',
            'is_receiver' => false,
            'detected_at' => '2026-11-21T10:00:00+00:00',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function liveDevice(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Unknown Device',
            'class' => 'Mouse',
            'status' => 'OK',
            'vid' => null,
            'pid' => null,
            'hardware_ids' => [],
            'instance_id' => '',
            'location' => '',
        ], $overrides);
    }
}
