<?php

namespace Tests\Feature;

use App\Models\Mouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MouseCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_returns_paginated_mice(): void
    {
        Mouse::factory()->count(3)->create();

        $response = $this->getJson('/api/mice');

        $response->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total']);
    }

    public function test_valid_payload_creates_mouse_and_returns_201(): void
    {
        $response = $this->postJson('/api/mice', [
            'brand' => 'Logitech',
            'model' => 'MX Master 3',
            'color' => 'Graphite',
            'connectivity_type' => '2.4GHz wireless',
            'notes' => 'Test mouse',
        ]);

        $response->assertCreated()
            ->assertJsonPath('brand', 'Logitech')
            ->assertJsonPath('model', 'MX Master 3')
            ->assertJsonPath('device_match_status', 'unmatched');

        $this->assertDatabaseHas('mice', ['brand' => 'Logitech', 'model' => 'MX Master 3']);
    }

    public function test_empty_payload_is_rejected(): void
    {
        $this->postJson('/api/mice', [])->assertUnprocessable()->assertJsonValidationErrors('brand');
    }

    public function test_update_changes_mouse_and_returns_200(): void
    {
        $mouse = Mouse::factory()->create(['brand' => 'Old Brand']);

        $response = $this->putJson("/api/mice/{$mouse->id}", [
            'brand' => 'New Brand',
            'model' => 'Updated Model',
        ]);

        $response->assertOk()->assertJsonPath('brand', 'New Brand');
        $this->assertDatabaseHas('mice', ['id' => $mouse->id, 'brand' => 'New Brand', 'model' => 'Updated Model']);
    }

    public function test_delete_removes_mouse_and_returns_message(): void
    {
        $mouse = Mouse::factory()->create();

        $response = $this->deleteJson("/api/mice/{$mouse->id}");

        $response->assertOk()->assertJsonPath('message', 'Mouse deleted successfully');
        $this->assertModelMissing($mouse);
    }

    public function test_show_returns_single_mouse(): void
    {
        $mouse = Mouse::factory()->create();

        $this->getJson("/api/mice/{$mouse->id}")
            ->assertOk()
            ->assertJsonPath('id', $mouse->id)
            ->assertJsonStructure(['photo_url', 'device_match_status']);
    }

    public function test_per_page_is_capped_at_100(): void
    {
        $this->getJson('/api/mice?per_page=500')->assertOk()->assertJsonPath('per_page', 100);
    }
}
