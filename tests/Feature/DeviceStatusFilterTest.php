<?php

namespace Tests\Feature;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Status filters and remove_photo behaviour for both device types. */
class DeviceStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_mouse_status_filters_distinguish_matched_suspected_and_unmatched(): void
    {
        $matched = Mouse::factory()->create();
        $suspected = Mouse::factory()->create();
        $unmatched = Mouse::factory()->create();

        DeviceMatch::factory()->create([
            'mouse_id' => $matched->id,
            'dongle_id' => Dongle::factory()->create()->id,
            'status' => 'matched',
        ]);
        DeviceMatch::factory()->create([
            'mouse_id' => $suspected->id,
            'dongle_id' => Dongle::factory()->create()->id,
            'status' => 'suspected',
        ]);
        DeviceMatch::factory()->create([
            'mouse_id' => $unmatched->id,
            'dongle_id' => Dongle::factory()->create()->id,
            'status' => 'unmatched',
        ]);

        $this->assertSame(
            [$matched->id],
            collect($this->getJson('/api/mice?status=matched')->assertOk()->json('data'))->pluck('id')->all(),
        );
        $this->assertSame(
            [$suspected->id],
            collect($this->getJson('/api/mice?status=suspected')->assertOk()->json('data'))->pluck('id')->all(),
        );

        $unmatchedIds = collect($this->getJson('/api/mice?status=unmatched')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertContains($unmatched->id, $unmatchedIds);
        $this->assertNotContains($matched->id, $unmatchedIds);
        $this->assertNotContains($suspected->id, $unmatchedIds);
    }

    public function test_dongle_status_filters_include_unmatched_dongles(): void
    {
        $matched = Dongle::factory()->create();
        $unmatched = Dongle::factory()->create();

        DeviceMatch::factory()->create([
            'mouse_id' => Mouse::factory()->create()->id,
            'dongle_id' => $matched->id,
            'status' => 'matched',
        ]);

        $matchedIds = collect($this->getJson('/api/dongles?status=matched')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertContains($matched->id, $matchedIds);
        $this->assertNotContains($unmatched->id, $matchedIds);

        $unmatchedIds = collect($this->getJson('/api/dongles?status=unmatched')->assertOk()->json('data'))
            ->pluck('id')->all();
        $this->assertContains($unmatched->id, $unmatchedIds);
        $this->assertNotContains($matched->id, $unmatchedIds);
    }

    public function test_remove_photo_via_put_json_deletes_mouse_photo(): void
    {
        Storage::fake('public');
        $path = $this->storePhoto('mice', 'mouse-remove.jpg');
        $mouse = Mouse::factory()->create(['photo_path' => $path]);

        $this->putJson("/api/mice/{$mouse->id}", [
            'brand' => $mouse->brand,
            'remove_photo' => 1,
        ])->assertOk()->assertJsonPath('photo_path', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($mouse->fresh()->photo_path);
    }

    public function test_remove_photo_via_put_json_deletes_dongle_photo(): void
    {
        Storage::fake('public');
        $path = $this->storePhoto('dongles', 'dongle-remove.png');
        $dongle = Dongle::factory()->create(['photo_path' => $path]);

        $this->putJson("/api/dongles/{$dongle->id}", [
            'remove_photo' => true,
        ])->assertOk()->assertJsonPath('photo_path', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($dongle->fresh()->photo_path);
    }

    private function storePhoto(string $directory, string $name): string
    {
        return UploadedFile::fake()
            ->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='))
            ->mimeType(str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg')
            ->store($directory, 'public');
    }
}
