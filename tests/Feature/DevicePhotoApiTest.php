<?php

namespace Tests\Feature;

use App\Models\DevicePhoto;
use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DevicePhotoApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_mice_index_includes_reference_photos_on_every_record(): void
    {
        $this->seed();

        $response = $this->getJson('/api/mice?per_page=100');

        $response->assertOk()->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total']);
        $this->assertNotEmpty($response->json('data'));

        foreach ($response->json('data') as $mouse) {
            $this->assertArrayHasKey('reference_photos', $mouse);
            $this->assertIsArray($mouse['reference_photos']);
        }

        $withPhotos = collect($response->json('data'))->firstWhere('mouse_id', 'MS-SEED001');
        $this->assertNotNull($withPhotos);
        $this->assertGreaterThanOrEqual(2, count($withPhotos['reference_photos']));
        $this->assertSame(
            ['id', 'photoable_type', 'photoable_id', 'photo_type', 'caption', 'path', 'source_url', 'source_name', 'is_seed', 'created_at', 'updated_at', 'url'],
            array_keys($withPhotos['reference_photos'][0]),
        );
    }

    public function test_dongles_index_includes_reference_photos_on_every_record(): void
    {
        $this->seed();

        $response = $this->getJson('/api/dongles?per_page=100');

        $response->assertOk()->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total']);
        $this->assertNotEmpty($response->json('data'));

        foreach ($response->json('data') as $dongle) {
            $this->assertArrayHasKey('reference_photos', $dongle);
            $this->assertIsArray($dongle['reference_photos']);
        }

        $withPhotos = collect($response->json('data'))->firstWhere('dongle_id', 'DG-SEED002');
        $this->assertNotNull($withPhotos);
        $this->assertGreaterThanOrEqual(2, count($withPhotos['reference_photos']));
    }

    public function test_reference_photos_are_scoped_to_their_own_device(): void
    {
        $this->seed();

        $mouse = Mouse::where('mouse_id', 'MS-SEED001')->firstOrFail();
        $dongle = Dongle::where('dongle_id', 'DG-SEED001')->firstOrFail();

        $this->assertTrue($mouse->referencePhotos->every(
            static fn (DevicePhoto $photo): bool => $photo->photoable_id === $mouse->id
                && $photo->photoable_type === $mouse->getMorphClass(),
        ));
        $this->assertTrue($dongle->referencePhotos->every(
            static fn (DevicePhoto $photo): bool => $photo->photoable_id === $dongle->id
                && $photo->photoable_type === $dongle->getMorphClass(),
        ));
    }

    public function test_device_photo_url_accessor_returns_an_asset_url(): void
    {
        $photo = new DevicePhoto(['path' => 'images/devices/mice/MS-SEED001/01-top.jpg']);

        $this->assertSame(asset('images/devices/mice/MS-SEED001/01-top.jpg'), $photo->url);
    }
}
