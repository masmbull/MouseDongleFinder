<?php

namespace Tests\Feature;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DongleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_dongles(): void
    {
        Dongle::factory()->count(2)->create();

        $this->getJson('/api/dongles')
            ->assertOk()
            ->assertJsonPath('total', 2)
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);
    }

    public function test_dongle_with_unknown_brand_can_be_created(): void
    {
        $response = $this->postJson('/api/dongles', [
            'model' => 'UnknownReceiver',
            'physical_description' => 'Small black receiver',
        ]);

        $response->assertCreated()->assertJsonPath('brand', null);
        $this->assertDatabaseHas('dongles', ['model' => 'UnknownReceiver', 'brand' => null]);
    }

    public function test_update_normalizes_vid_and_pid_to_uppercase(): void
    {
        $dongle = Dongle::factory()->create();

        $this->putJson("/api/dongles/{$dongle->id}", [
            'vid' => '046d',
            'pid' => 'c52b',
        ])->assertOk()->assertJsonPath('vid', '046D')->assertJsonPath('pid', 'C52B');

        $this->assertDatabaseHas('dongles', ['id' => $dongle->id, 'vid' => '046D', 'pid' => 'C52B']);
    }

    public function test_delete_removes_dongle(): void
    {
        $dongle = Dongle::factory()->create();

        $this->deleteJson("/api/dongles/{$dongle->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Dongle deleted successfully');

        $this->assertModelMissing($dongle);
    }

    public function test_unmatched_filter_excludes_dongles_with_active_matches(): void
    {
        $activeDongle = Dongle::factory()->create();
        $inactiveDongle = Dongle::factory()->create();
        $freeDongle = Dongle::factory()->create();

        DeviceMatch::factory()->create([
            'mouse_id' => Mouse::factory()->create()->id,
            'dongle_id' => $activeDongle->id,
            'status' => 'matched',
        ]);
        DeviceMatch::factory()->create([
            'mouse_id' => Mouse::factory()->create()->id,
            'dongle_id' => $inactiveDongle->id,
            'status' => 'unmatched',
        ]);

        $response = $this->getJson('/api/dongles?status=unmatched');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($inactiveDongle->id, $ids);
        $this->assertContains($freeDongle->id, $ids);
        $this->assertNotContains($activeDongle->id, $ids);
    }

    public function test_matched_status_filter_returns_only_confirmed_dongles(): void
    {
        $matched = Dongle::factory()->create();
        $other = Dongle::factory()->create();

        DeviceMatch::factory()->create([
            'mouse_id' => Mouse::factory()->create()->id,
            'dongle_id' => $matched->id,
            'status' => 'matched',
        ]);

        $response = $this->getJson('/api/dongles?status=matched');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($matched->id, $ids);
        $this->assertNotContains($other->id, $ids);
    }

    public function test_per_page_is_capped_at_100(): void
    {
        $this->getJson('/api/dongles?per_page=500')->assertOk()->assertJsonPath('per_page', 100);
    }
}
