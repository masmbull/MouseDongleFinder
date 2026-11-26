<?php

namespace Tests\Feature;

use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_mice_can_be_searched_by_id_brand_and_model(): void
    {
        $target = Mouse::factory()->create([
            'mouse_id' => 'MS-ABC12345',
            'brand' => 'Logitech',
            'model' => 'MX Anywhere',
        ]);
        Mouse::factory()->create(['brand' => 'Razer', 'model' => 'DeathAdder']);

        foreach (['MS-ABC12345', 'Logitech', 'MX Anywhere'] as $term) {
            $response = $this->getJson('/api/mice?search='.urlencode($term));

            $response->assertOk()->assertJsonPath('total', 1);
            $this->assertSame($target->id, $response->json('data.0.id'));
        }
    }

    public function test_dongles_can_be_searched_by_id_brand_model_and_vid(): void
    {
        $target = Dongle::factory()->create([
            'dongle_id' => 'DG-ZYX98765',
            'brand' => 'Logitech',
            'model' => 'Unifying',
            'vid' => '046D',
        ]);
        Dongle::factory()->create(['brand' => 'Razer', 'model' => 'Chroma', 'vid' => '1532']);

        foreach (['DG-ZYX98765', 'Logitech', 'Unifying', '046D'] as $term) {
            $response = $this->getJson('/api/dongles?search='.urlencode($term));

            $response->assertOk()->assertJsonPath('total', 1);
            $this->assertSame($target->id, $response->json('data.0.id'));
        }
    }

    public function test_brand_filter_combines_with_search(): void
    {
        $target = Mouse::factory()->create(['brand' => 'Logitech', 'model' => 'MX Master']);
        Mouse::factory()->create(['brand' => 'Razer', 'model' => 'MX Master']);

        $this->getJson('/api/mice?search=MX&brand=Logitech')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_no_match_returns_empty_paginator(): void
    {
        Mouse::factory()->create(['brand' => 'Logitech']);

        $this->getJson('/api/mice?search=NoSuchMouse')
            ->assertOk()
            ->assertJsonPath('total', 0)
            ->assertJsonPath('data', []);
    }
}
