<?php

namespace Tests\Feature;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\Download;
use App\Models\Identification;
use App\Models\Mouse;
use App\Models\UsbScan;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_returns_valid_json_with_zeroed_stats_on_empty_database(): void
    {
        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonStructure([
                'stats' => [
                    'total_mice',
                    'total_dongles',
                    'matched_pairs',
                    'suspected_pairs',
                    'unmatched_dongles',
                    'unmatched_mice',
                    'unknown_devices',
                ],
                'recent_mice',
                'recent_dongles',
                'recent_matches',
                'status_overview',
            ])
            ->assertJsonPath('stats.total_mice', 0)
            ->assertJsonPath('stats.total_dongles', 0)
            ->assertJsonPath('stats.matched_pairs', 0);
    }

    public function test_dashboard_stats_update_after_adding_devices_and_matches(): void
    {
        $mouseA = Mouse::factory()->create();
        $mouseB = Mouse::factory()->create();
        $dongleA = Dongle::factory()->create();
        $dongleB = Dongle::factory()->create();

        DeviceMatch::factory()->create([
            'mouse_id' => $mouseA->id,
            'dongle_id' => $dongleA->id,
            'status' => 'matched',
        ]);
        DeviceMatch::factory()->create([
            'mouse_id' => $mouseB->id,
            'dongle_id' => $dongleB->id,
            'status' => 'suspected',
        ]);

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.total_mice', 2)
            ->assertJsonPath('stats.total_dongles', 2)
            ->assertJsonPath('stats.matched_pairs', 1)
            ->assertJsonPath('stats.suspected_pairs', 1)
            ->assertJsonPath('stats.unmatched_mice', 0)
            ->assertJsonPath('stats.unmatched_dongles', 0)
            ->assertJsonPath('status_overview.matched', 1)
            ->assertJsonPath('status_overview.suspected', 1);
    }

    public function test_all_get_endpoints_return_valid_json(): void
    {
        $mouse = Mouse::factory()->create();
        $dongle = Dongle::factory()->create();
        $match = DeviceMatch::factory()->create([
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'matched',
        ]);
        $identification = Identification::factory()->create();
        $scan = UsbScan::factory()->create();
        $download = Download::factory()->create();

        $endpoints = [
            '/api/dashboard',
            '/api/mice',
            "/api/mice/{$mouse->id}",
            '/api/dongles',
            "/api/dongles/{$dongle->id}",
            '/api/matches',
            "/api/matches/{$match->id}",
            '/api/usb-scans',
            '/api/usb-scans/latest',
            "/api/usb-scans/{$scan->id}",
            '/api/identifications',
            "/api/identifications/{$identification->id}",
            '/api/usb-ids/lookup?vid=046D',
            '/api/research/software',
            '/api/downloads',
            "/api/downloads/{$download->id}",
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertOk();
        }
    }

    public function test_dashboard_reflects_deleted_devices(): void
    {
        $mouse = Mouse::factory()->create();
        $dongle = Dongle::factory()->create();

        DeviceMatch::factory()->create([
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'matched',
        ]);

        $this->deleteJson("/api/matches/{$dongle->match->id}")->assertOk();
        $this->deleteJson("/api/mice/{$mouse->id}")->assertOk();

        $this->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('stats.total_mice', 0)
            ->assertJsonPath('stats.matched_pairs', 0);
    }
}
