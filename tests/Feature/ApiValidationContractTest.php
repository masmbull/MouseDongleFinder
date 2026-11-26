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

/** Validation, enum and not-found contracts shared across the API. */
class ApiValidationContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_mouse_store_requires_a_brand(): void
    {
        $this->postJson('/api/mice', ['model' => 'MX Master 3S'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brand');

        $this->assertDatabaseCount('mice', 0);
    }

    public function test_mouse_update_requires_a_brand(): void
    {
        $mouse = Mouse::factory()->create(['brand' => 'Logitech']);

        $this->putJson("/api/mice/{$mouse->id}", ['brand' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brand');

        $this->assertSame('Logitech', $mouse->fresh()->brand);
    }

    public function test_matches_reject_an_invalid_status_enum(): void
    {
        $mouse = Mouse::factory()->create();
        $dongle = Dongle::factory()->create();

        $this->postJson('/api/matches', [
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'definitely-matched',
        ])->assertUnprocessable()->assertJsonValidationErrors('status');

        $this->assertDatabaseCount('device_matches', 0);
    }

    public function test_matches_require_existing_devices(): void
    {
        $this->postJson('/api/matches', [
            'mouse_id' => 9999,
            'dongle_id' => 9999,
            'status' => 'matched',
        ])->assertUnprocessable()->assertJsonValidationErrors(['mouse_id', 'dongle_id']);
    }

    public function test_duplicate_active_match_returns_the_existing_relationship(): void
    {
        $mouse = Mouse::factory()->create();
        $dongle = Dongle::factory()->create();
        $existing = DeviceMatch::factory()->create([
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'matched',
        ]);
        $otherMouse = Mouse::factory()->create();

        $this->postJson('/api/matches', [
            'mouse_id' => $otherMouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'suspected',
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'This mouse or dongle already has an active match.')
            ->assertJsonPath('existing_match.id', $existing->id)
            ->assertJsonStructure(['error', 'existing_match' => ['id', 'mouse', 'dongle', 'status']]);
    }

    public function test_missing_resources_return_404(): void
    {
        $endpoints = [
            '/api/mice/99999',
            '/api/dongles/99999',
            '/api/matches/99999',
            '/api/usb-scans/99999',
            '/api/identifications/99999',
            '/api/downloads/99999',
        ];

        foreach ($endpoints as $endpoint) {
            $this->getJson($endpoint)->assertNotFound();
        }

        $this->putJson('/api/mice/99999', ['brand' => 'Logitech'])->assertNotFound();
        $this->deleteJson('/api/dongles/99999')->assertNotFound();
    }

    public function test_unknown_api_routes_are_not_silently_treated_as_api_success(): void
    {
        // The SPA catch-all in routes/web.php serves unknown GET paths (200),
        // while other verbs to the same unknown path are rejected with 405.
        $this->getJson('/api/this-route-does-not-exist')
            ->assertOk()
            ->assertSee('app');

        $this->postJson('/api/this-route-does-not-exist')->assertStatus(405);
    }

    public function test_factories_create_records_for_the_not_found_matrix(): void
    {
        $this->assertModelExists(Mouse::factory()->create());
        $this->assertModelExists(Dongle::factory()->create());
        $this->assertModelExists(UsbScan::factory()->create());
        $this->assertModelExists(Identification::factory()->create());
        $this->assertModelExists(Download::factory()->create());
    }
}
