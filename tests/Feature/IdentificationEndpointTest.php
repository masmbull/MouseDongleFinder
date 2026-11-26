<?php

namespace Tests\Feature;

use App\Models\Dongle;
use App\Models\Identification;
use App\Services\ImageAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Focused coverage of the identification API contract; all analysis is local. */
class IdentificationEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_known_receiver_has_curated_evidence_queries_and_unknowns(): void
    {
        $response = $this->postJson('/api/identifications/analyze', [
            'vid' => '046D',
            'pid' => 'C52B',
            'hardware_id' => 'USB\\VID_046D&PID_C52B&REV_0101',
            'vid_pid_source' => 'usb_scan',
        ])->assertOk()
            ->assertJsonPath('vid', '046D')
            ->assertJsonPath('pid', 'C52B')
            ->assertJsonPath('confidence', 'high')
            ->assertJsonPath('probable_manufacturer', 'Logitech')
            ->assertJsonPath('probable_model', 'Unifying Receiver')
            ->assertJsonPath('receiver_family', 'Unifying')
            ->assertJsonPath('device_category', 'wireless receiver');

        $evidenceTypes = array_column($response->json('evidence'), 'type');
        $this->assertContains('detected', $evidenceTypes);
        $this->assertContains('curated_reference', $evidenceTypes);

        foreach ($response->json('evidence') as $item) {
            $this->assertArrayHasKey('type', $item);
            $this->assertArrayHasKey('detail', $item);
            $this->assertArrayHasKey('source', $item);
        }

        $this->assertGreaterThanOrEqual(6, count($response->json('search_queries')));
        $this->assertLessThanOrEqual(10, count($response->json('search_queries')));
        $this->assertContains('"046D:C52B" USB VID PID', $response->json('search_queries'));
        $unknowns = $response->json('unknowns');
        $this->assertNotEmpty($unknowns);
        $this->assertStringContainsString('Physical pairing test', end($unknowns));
    }

    public function test_analyze_unknown_identifiers_never_invents_a_model(): void
    {
        $response = $this->postJson('/api/identifications/analyze', [
            'vid' => 'ABCD',
            'pid' => '1234',
        ])->assertOk()
            ->assertJsonPath('confidence', 'unknown')
            ->assertJsonPath('probable_manufacturer', null)
            ->assertJsonPath('probable_model', null)
            ->assertJsonPath('receiver_family', null)
            ->assertJsonPath('pairing_utility', null)
            ->assertJsonPath('support_url', null);

        $this->assertSame([], $response->json('compatible_mice'));
        $this->assertNotContains('curated_reference', array_column($response->json('evidence'), 'type'));
        $this->assertNotEmpty($response->json('unknowns'));
    }

    public function test_store_creates_a_normalized_identification_record(): void
    {
        $dongle = Dongle::factory()->create();

        $response = $this->postJson('/api/identifications', [
            'dongle_id' => $dongle->id,
            'vid' => '046d',
            'pid' => 'c52b',
            'probable_manufacturer' => 'Logitech',
            'receiver_family' => 'Unifying',
            'confidence' => 'medium',
        ])->assertCreated()
            ->assertJsonPath('vid', '046D')
            ->assertJsonPath('pid', 'C52B')
            ->assertJsonPath('dongle.id', $dongle->id);

        $this->assertDatabaseHas('identifications', [
            'id' => $response->json('id'),
            'dongle_id' => $dongle->id,
            'vid' => '046D',
            'pid' => 'C52B',
        ]);
    }

    public function test_index_and_show_return_saved_identifications(): void
    {
        $identification = Identification::factory()->create();

        $this->getJson('/api/identifications')
            ->assertOk()
            ->assertJsonPath('total', 1)
            ->assertJsonStructure(['data', 'current_page', 'last_page', 'per_page', 'total']);

        $this->getJson('/api/identifications?confidence=unknown')
            ->assertOk()
            ->assertJsonPath('total', $identification->confidence === 'unknown' ? 1 : 0);

        $this->getJson("/api/identifications/{$identification->id}")
            ->assertOk()
            ->assertJsonPath('id', $identification->id)
            ->assertJsonStructure(['dongle', 'evidence', 'research_sources']);
    }

    public function test_save_analysis_route_persists_an_analysis_payload(): void
    {
        $dongle = Dongle::factory()->create();

        $response = $this->postJson("/api/identifications/{$dongle->id}/save-analysis", [
            'analysis' => [
                'dongle_id' => $dongle->id,
                'vid' => '046D',
                'pid' => 'C52B',
                'probable_manufacturer' => 'Logitech',
                'confidence' => 'high',
                'evidence' => [['type' => 'curated_reference', 'detail' => 'Documented', 'source' => 'docs']],
            ],
        ])->assertCreated()
            ->assertJsonPath('confidence', 'high')
            ->assertJsonPath('vid', '046D');

        $this->assertDatabaseHas('identifications', [
            'id' => $response->json('id'),
            'dongle_id' => $dongle->id,
            'confidence' => 'high',
        ]);
    }

    public function test_ocr_uses_the_image_service_and_reports_unavailable_honestly(): void
    {
        $result = [
            'available' => false,
            'extracted_text' => null,
            'fields' => ['fcc_id' => null, 'ic_id' => null, 'model' => null, 'serial' => null],
            'message' => 'OCR is not configured. Manual entry remains available.',
        ];

        $this->mock(ImageAnalysisService::class)
            ->shouldReceive('analyze')
            ->once()
            ->andReturn($result);

        $this->post('/api/identifications/ocr', [
            'photo' => UploadedFile::fake()->createWithContent('label.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFAAH/q842iQAAAABJRU5ErkJggg=='))->mimeType('image/png'),
            'photo_type' => 'label',
        ])->assertOk()
            ->assertJsonPath('available', false)
            ->assertJsonPath('message', $result['message'])
            ->assertJsonPath('extracted_text', null);
    }

    public function test_ocr_requires_an_image(): void
    {
        $this->postJson('/api/identifications/ocr', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('photo');
    }
}
