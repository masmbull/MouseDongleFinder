<?php

namespace Tests\Feature;

use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_mouse_photo_upload_stores_file_and_returns_photo_url(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/mice', [
            'brand' => 'Logitech',
            'photo' => $this->fakeImage('mouse.jpg'),
        ]);

        $response->assertCreated();
        $path = $response->json('photo_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->assertStringContainsString($path, $response->json('photo_url'));
    }

    public function test_dongle_photo_upload_stores_file(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/dongles', [
            'photo' => $this->fakeImage('dongle.png'),
        ]);

        $response->assertCreated();
        $path = $response->json('photo_path');
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_new_photo_replaces_previous_mouse_photo(): void
    {
        Storage::fake('public');
        $mouse = Mouse::factory()->create(['photo_path' => $this->storePhoto('mice', 'old.jpg')]);
        $oldPath = $mouse->photo_path;

        $response = $this->post("/api/mice/{$mouse->id}", [
            '_method' => 'PUT',
            'brand' => $mouse->brand,
            'photo' => $this->fakeImage('new.jpg'),
        ]);

        $response->assertOk();
        Storage::disk('public')->assertMissing($oldPath);
        Storage::disk('public')->assertExists($response->json('photo_path'));
    }

    public function test_remove_photo_deletes_mouse_file_and_clears_path(): void
    {
        Storage::fake('public');
        $mouse = Mouse::factory()->create(['photo_path' => $this->storePhoto('mice', 'remove-me.jpg')]);
        $path = $mouse->photo_path;

        $this->post("/api/mice/{$mouse->id}", [
            '_method' => 'PUT',
            'brand' => $mouse->brand,
            'remove_photo' => '1',
        ])->assertOk()->assertJsonPath('photo_path', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($mouse->fresh()->photo_path);
    }

    public function test_remove_photo_deletes_dongle_file_and_clears_path(): void
    {
        Storage::fake('public');
        $dongle = Dongle::factory()->create(['photo_path' => $this->storePhoto('dongles', 'remove-me.png')]);
        $path = $dongle->photo_path;

        $this->post("/api/dongles/{$dongle->id}", [
            '_method' => 'PUT',
            'remove_photo' => '1',
        ])->assertOk()->assertJsonPath('photo_path', null);

        Storage::disk('public')->assertMissing($path);
        $this->assertNull($dongle->fresh()->photo_path);
    }

    public function test_deleting_mouse_also_deletes_its_photo(): void
    {
        Storage::fake('public');
        $mouse = Mouse::factory()->create(['photo_path' => $this->storePhoto('mice', 'delete-me.jpg')]);
        $path = $mouse->photo_path;

        $this->deleteJson("/api/mice/{$mouse->id}")->assertOk();

        Storage::disk('public')->assertMissing($path);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->post('/api/mice', [
            'brand' => 'Logitech',
            'photo' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain'),
        ])->assertUnprocessable()->assertJsonValidationErrors('photo');
    }

    private function storePhoto(string $directory, string $name): string
    {
        return $this->fakeImage($name)->store($directory, 'public');
    }

    private function fakeImage(string $name): UploadedFile
    {
        return UploadedFile::fake()
            ->createWithContent($name, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='))
            ->mimeType(str_ends_with($name, '.png') ? 'image/png' : 'image/jpeg');
    }
}
