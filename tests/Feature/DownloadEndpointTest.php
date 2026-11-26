<?php

namespace Tests\Feature;

use App\Models\Download;
use App\Services\DownloadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * Download tests never touch the network: Http::fake supplies one small file and
 * the service is mocked for explorer interaction so nothing is ever executed.
 */
class DownloadEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetch_saves_a_mocked_https_file_with_a_sha256_and_persists_the_record(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            '127.0.0.1/*' => Http::response('manual bytes', 200, ['Content-Length' => '12']),
        ]);

        $model = 'Model'.Str::random(8);
        $response = $this->postJson('/api/downloads/fetch', [
            'source_url' => 'https://127.0.0.1/manual.pdf',
            'manufacturer' => 'Logitech',
            'model' => $model,
            'filename' => 'manual.pdf',
            'source_type' => 'official',
            'software_type' => 'manual',
        ])->assertCreated()
            ->assertJsonPath('download.download_status', 'completed')
            ->assertJsonPath('download.manufacturer', 'Logitech')
            ->assertJsonPath('download.model', $model)
            ->assertJsonPath('download.file_size', 12);

        $path = $response->json('download.file_path');
        $this->assertStringContainsString(
            'downloads'.DIRECTORY_SEPARATOR.'Logitech'.DIRECTORY_SEPARATOR.$model.DIRECTORY_SEPARATOR,
            $path,
        );
        $this->assertFileExists($path);
        $this->assertSame(hash('sha256', 'manual bytes'), $response->json('download.sha256_hash'));
        $this->assertSame(hash('sha256', 'manual bytes'), hash_file('sha256', $path));
        $this->assertDatabaseHas('downloads', [
            'id' => $response->json('download.id'),
            'file_path' => $path,
            'sha256_hash' => hash('sha256', 'manual bytes'),
        ]);

        @unlink($path);
        @rmdir(dirname($path));
    }

    public function test_http_urls_are_rejected_before_any_service_call(): void
    {
        $this->mock(DownloadService::class)->shouldNotReceive('fetch');

        $this->postJson('/api/downloads/fetch', [
            'source_url' => 'http://example.com/manual.pdf',
        ])->assertUnprocessable()->assertJsonValidationErrors('source_url');

        $this->assertDatabaseCount('downloads', 0);
    }

    public function test_executables_require_explicit_confirmation(): void
    {
        $this->mock(DownloadService::class)->shouldNotReceive('fetch');

        $this->postJson('/api/downloads/fetch', [
            'source_url' => 'https://example.com/setup.exe',
        ])->assertUnprocessable()
            ->assertJsonPath('requires_confirmation', true)
            ->assertJsonPath('error', 'This is an executable file. Set confirmed=true only after you trust the source.');
    }

    public function test_service_errors_are_returned_as_structured_422_responses(): void
    {
        $this->mock(DownloadService::class)
            ->shouldReceive('fetch')
            ->once()
            ->andThrow(new \RuntimeException('The download host could not be resolved: example.invalid.'));

        $this->postJson('/api/downloads/fetch', [
            'source_url' => 'https://example.invalid/manual.pdf',
        ])->assertUnprocessable()
            ->assertJsonPath('error', 'The download host could not be resolved: example.invalid.');

        $this->assertDatabaseCount('downloads', 0);
    }

    public function test_verify_accepts_matching_hash_and_rejects_tampered_hash(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mdf-verify-');
        file_put_contents($path, 'original bytes');
        $download = Download::factory()->create([
            'file_path' => $path,
            'sha256_hash' => hash('sha256', 'original bytes'),
        ]);

        $this->getJson("/api/downloads/{$download->id}/verify")
            ->assertOk()
            ->assertJsonPath('verified', true)
            ->assertJsonPath('computed_hash', hash('sha256', 'original bytes'));

        file_put_contents($path, 'tampered bytes');

        $this->getJson("/api/downloads/{$download->id}/verify")
            ->assertOk()
            ->assertJsonPath('verified', false)
            ->assertJsonPath('stored_hash', hash('sha256', 'original bytes'));

        @unlink($path);
    }

    public function test_open_delegates_to_the_service_and_never_launches_the_download(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mdf-open-');
        file_put_contents($path, 'do not execute');
        $download = Download::factory()->create(['file_path' => $path]);

        $this->mock(DownloadService::class)
            ->shouldReceive('open')
            ->once()
            ->with(Mockery::on(fn (Download $argument): bool => $argument->id === $download->id))
            ->andReturn(['opened' => true, 'path' => dirname($path)]);

        $this->postJson("/api/downloads/{$download->id}/open")
            ->assertOk()
            ->assertJsonPath('opened', true);

        $this->assertFileExists($path);
        $this->assertSame('do not execute', file_get_contents($path));

        @unlink($path);
    }

    public function test_index_filters_and_show_return_download_records(): void
    {
        $matching = Download::factory()->create(['source_type' => 'official', 'download_status' => 'completed']);
        Download::factory()->create(['source_type' => 'third-party', 'download_status' => 'failed']);

        $this->getJson('/api/downloads?source_type=official')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.id', $matching->id)
            ->assertJsonStructure(['data', 'current_page', 'per_page', 'total']);

        $this->getJson('/api/downloads?download_status=failed')
            ->assertOk()
            ->assertJsonPath('total', 1);

        $this->getJson("/api/downloads/{$matching->id}")
            ->assertOk()
            ->assertJsonPath('id', $matching->id)
            ->assertJsonStructure(['dongle', 'identification']);
    }

    public function test_destroy_removes_the_file_and_the_record(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'mdf-delete-');
        file_put_contents($path, 'delete me');
        $download = Download::factory()->create(['file_path' => $path]);

        $this->deleteJson("/api/downloads/{$download->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Download record deleted');

        $this->assertFileDoesNotExist($path);
        $this->assertModelMissing($download);
    }
}
