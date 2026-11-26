<?php

namespace Tests\Feature;

use App\Models\Download;
use App\Services\DownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DownloadTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_url_is_rejected_before_any_download(): void
    {
        $this->mock(DownloadService::class)->shouldNotReceive('fetch');

        $this->postJson('/api/downloads/fetch', [
            'source_url' => 'http://example.com/manual.pdf',
        ])->assertUnprocessable()->assertJsonValidationErrors('source_url');

        $this->assertDatabaseCount('downloads', 0);
    }

    public function test_executable_without_confirmation_is_rejected(): void
    {
        $this->mock(DownloadService::class)->shouldNotReceive('fetch');

        $this->postJson('/api/downloads/fetch', [
            'source_url' => 'https://example.com/setup.exe',
        ])->assertUnprocessable()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('error', 'This is an executable file. Set confirmed=true only after you trust the source.');

        $this->assertDatabaseCount('downloads', 0);
    }

    public function test_confirmed_download_creates_completed_record(): void
    {
        $metadata = [
            'filename' => 'setup.exe',
            'file_path' => base_path('downloads/Logitech/Unifying/setup.exe'),
            'source_url' => 'https://example.com/setup.exe',
            'final_url' => 'https://example.com/setup.exe',
            'source_type' => 'third-party',
            'manufacturer' => 'Logitech',
            'model' => 'Unifying',
            'software_type' => 'pairing',
            'file_size' => 12345,
            'sha256_hash' => str_repeat('a', 64),
        ];

        $this->mock(DownloadService::class)
            ->shouldReceive('fetch')
            ->once()
            ->andReturn($metadata);

        $response = $this->postJson('/api/downloads/fetch', [
            'source_url' => 'https://example.com/setup.exe',
            'confirmed' => true,
            'source_type' => 'third-party',
            'software_type' => 'pairing',
        ]);

        $response->assertCreated()
            ->assertJsonPath('download.download_status', 'completed')
            ->assertJsonPath('download.file_size', 12345)
            ->assertJsonPath('download.source_type', 'third-party')
            ->assertJsonStructure(['download', 'message']);

        $this->assertDatabaseHas('downloads', [
            'filename' => 'setup.exe',
            'download_status' => 'completed',
            'sha256_hash' => str_repeat('a', 64),
        ]);
    }

    public function test_non_executable_download_is_recorded(): void
    {
        $this->mock(DownloadService::class)
            ->shouldReceive('fetch')
            ->once()
            ->andReturn([
                'filename' => 'manual.pdf',
                'file_path' => base_path('downloads/Logitech/Unknown/manual.pdf'),
                'source_url' => 'https://example.com/manual.pdf',
                'final_url' => 'https://example.com/manual.pdf',
                'source_type' => 'official',
                'manufacturer' => 'Unknown',
                'model' => 'Unknown',
                'software_type' => 'manual',
                'file_size' => 2048,
                'sha256_hash' => str_repeat('b', 64),
            ]);

        $this->postJson('/api/downloads/fetch', [
            'source_url' => 'https://example.com/manual.pdf',
        ])->assertCreated()->assertJsonPath('download.source_type', 'official');
    }

    public function test_verify_and_open_endpoints_delegate_to_service(): void
    {
        $download = Download::factory()->create();

        $this->mock(DownloadService::class)
            ->shouldReceive('verify')
            ->once()
            ->andReturn(['verified' => true, 'computed_hash' => 'hash', 'stored_hash' => 'hash'])
            ->shouldReceive('open')
            ->once()
            ->andReturn(['opened' => true, 'path' => dirname($download->file_path)]);

        $this->getJson("/api/downloads/{$download->id}/verify")
            ->assertOk()
            ->assertJsonPath('verified', true);

        $this->postJson("/api/downloads/{$download->id}/open")
            ->assertOk()
            ->assertJsonPath('opened', true);
    }

    public function test_destroy_deletes_downloaded_file_and_record(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mdf-test-');
        file_put_contents($path, 'test content');
        $download = Download::factory()->create(['file_path' => $path]);

        $this->deleteJson("/api/downloads/{$download->id}")->assertOk();

        $this->assertFileDoesNotExist($path);
        $this->assertModelMissing($download);
    }
}
