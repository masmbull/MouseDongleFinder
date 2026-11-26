<?php

namespace Tests\Feature;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_match_can_be_created(): void
    {
        $mouse = Mouse::factory()->create();
        $dongle = Dongle::factory()->create();

        $response = $this->postJson('/api/matches', [
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'matched',
            'notes' => 'Physically verified',
        ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'matched')
            ->assertJsonPath('mouse.id', $mouse->id)
            ->assertJsonPath('dongle.id', $dongle->id);

        $this->assertDatabaseHas('device_matches', [
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'matched',
        ]);
    }

    public function test_same_dongle_cannot_be_active_with_a_second_mouse(): void
    {
        $dongle = Dongle::factory()->create();
        $mouseA = Mouse::factory()->create();
        $mouseB = Mouse::factory()->create();

        DeviceMatch::factory()->create([
            'mouse_id' => $mouseA->id,
            'dongle_id' => $dongle->id,
            'status' => 'matched',
        ]);

        $response = $this->postJson('/api/matches', [
            'mouse_id' => $mouseB->id,
            'dongle_id' => $dongle->id,
            'status' => 'suspected',
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('error', 'This mouse or dongle already has an active match.')
            ->assertJsonStructure(['error', 'existing_match']);

        $this->assertDatabaseCount('device_matches', 1);
    }

    public function test_same_mouse_cannot_be_active_with_a_second_dongle(): void
    {
        $mouse = Mouse::factory()->create();
        $dongleA = Dongle::factory()->create();
        $dongleB = Dongle::factory()->create();

        DeviceMatch::factory()->create([
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongleA->id,
            'status' => 'suspected',
        ]);

        $this->postJson('/api/matches', [
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongleB->id,
            'status' => 'matched',
        ])->assertUnprocessable()->assertJsonStructure(['error', 'existing_match']);

        $this->assertDatabaseCount('device_matches', 1);
    }

    public function test_transition_into_matched_is_blocked_when_mouse_already_has_active_match(): void
    {
        $mouse = Mouse::factory()->create();
        $dongleA = Dongle::factory()->create();
        $dongleB = Dongle::factory()->create();

        $activeMatch = DeviceMatch::factory()->create([
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongleA->id,
            'status' => 'suspected',
        ]);
        $pendingMatch = DeviceMatch::factory()->create([
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongleB->id,
            'status' => 'unmatched',
        ]);

        $this->putJson("/api/matches/{$pendingMatch->id}", ['status' => 'matched'])
            ->assertUnprocessable()
            ->assertJsonPath('existing_match.id', $activeMatch->id);

        $this->assertDatabaseHas('device_matches', ['id' => $pendingMatch->id, 'status' => 'unmatched']);
    }

    public function test_same_match_can_be_updated_to_matched_without_self_conflict(): void
    {
        $match = DeviceMatch::factory()->create([
            'mouse_id' => Mouse::factory()->create()->id,
            'dongle_id' => Dongle::factory()->create()->id,
            'status' => 'suspected',
        ]);

        $this->putJson("/api/matches/{$match->id}", ['status' => 'matched'])
            ->assertOk()
            ->assertJsonPath('status', 'matched');

        $this->assertDatabaseHas('device_matches', ['id' => $match->id, 'status' => 'matched']);
    }

    public function test_removing_match_returns_both_devices_to_unmatched(): void
    {
        $mouse = Mouse::factory()->create();
        $dongle = Dongle::factory()->create();
        $match = DeviceMatch::factory()->create([
            'mouse_id' => $mouse->id,
            'dongle_id' => $dongle->id,
            'status' => 'matched',
        ]);

        $this->deleteJson("/api/matches/{$match->id}")
            ->assertOk()
            ->assertJsonPath('message', 'DeviceMatch removed successfully');

        $this->assertModelMissing($match);
        $this->assertSame('unmatched', $mouse->fresh()->device_match_status);
        $this->assertSame('unmatched', $dongle->fresh()->device_match_status);
    }
}
