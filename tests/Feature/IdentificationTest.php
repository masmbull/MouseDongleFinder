<?php

namespace Tests\Feature;

use App\Models\UsbProduct;
use App\Models\UsbVendor;
use App\Services\ImageAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IdentificationTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<int, string> */
    private const RESULT_KEYS = [
        'vid', 'pid', 'vendor_name', 'product_name', 'probable_manufacturer', 'probable_model',
        'device_category', 'wireless_technology', 'frequency', 'receiver_family', 'compatible_mice',
        'pairing_utility', 'support_url', 'confidence', 'evidence', 'candidates',
        'recommended_software', 'research_sources', 'search_queries', 'unknowns',
        'detected', 'manual', 'online', 'inferred',
    ];

    public function test_analyze_returns_high_confidence_curated_result_for_known_receiver(): void
    {
        $response = $this->postJson('/api/identifications/analyze', [
            'vid' => '046d',
            'pid' => 'c52b',
            'hardware_id' => 'USB\\VID_046D&PID_C52B&REV_0101',
            'vid_pid_source' => 'usb_scan',
        ]);

        $response->assertOk();

        foreach (self::RESULT_KEYS as $key) {
            $this->assertArrayHasKey($key, $response->json());
        }

        $response->assertJsonPath('vid', '046D')
            ->assertJsonPath('pid', 'C52B')
            ->assertJsonPath('confidence', 'high')
            ->assertJsonPath('probable_manufacturer', 'Logitech')
            ->assertJsonPath('receiver_family', 'Unifying');

        $this->assertContains('curated_reference', array_column($response->json('evidence'), 'type'));
        $this->assertGreaterThanOrEqual(6, count($response->json('search_queries')));
        $this->assertLessThanOrEqual(10, count($response->json('search_queries')));
        $this->assertIsArray($response->json('unknowns'));
    }

    public function test_analyze_with_unknown_identifiers_stays_unknown_and_lists_unknowns(): void
    {
        $response = $this->postJson('/api/identifications/analyze', [
            'vid' => '9999',
            'pid' => '8888',
        ]);

        $response->assertOk()
            ->assertJsonPath('confidence', 'unknown')
            ->assertJsonPath('vendor_name', null)
            ->assertJsonPath('probable_manufacturer', null);

        $this->assertNotEmpty($response->json('unknowns'));
        $this->assertSame([], $response->json('compatible_mice'));
    }

    public function test_analyze_parses_identifiers_from_hardware_id(): void
    {
        $this->postJson('/api/identifications/analyze', [
            'hardware_id' => 'USB\\VID_046D&PID_C548&REV_0100',
        ])->assertOk()
            ->assertJsonPath('vid', '046D')
            ->assertJsonPath('pid', 'C548')
            ->assertJsonPath('receiver_family', 'Bolt')
            ->assertJsonPath('confidence', 'high');
    }

    public function test_save_analysis_endpoint_persists_a_record(): void
    {
        $response = $this->postJson('/api/identifications/save-analysis', [
            'vid' => '046D',
            'pid' => 'C52B',
            'probable_manufacturer' => 'Logitech',
            'receiver_family' => 'Unifying',
            'confidence' => 'high',
            'evidence' => [['type' => 'curated_reference', 'detail' => 'Documented', 'source' => 'https://support.logi.com/']],
            'compatible_mice' => [],
        ]);

        $response->assertCreated()->assertJsonPath('confidence', 'high');
        $this->assertDatabaseHas('identifications', ['vid' => '046D', 'pid' => 'C52B', 'confidence' => 'high']);
    }

    public function test_usb_ids_lookup_uses_local_rows(): void
    {
        UsbVendor::query()->create(['vid' => '046D', 'vendor_name' => 'Logitech, Inc.']);
        UsbProduct::query()->create(['vid' => '046D', 'pid' => 'C52B', 'product_name' => 'USB Receiver (Unifying)']);

        $this->getJson('/api/usb-ids/lookup?vid=046d&pid=c52b')
            ->assertOk()
            ->assertJsonPath('vid', '046D')
            ->assertJsonPath('pid', 'C52B')
            ->assertJsonPath('vendor_name', 'Logitech, Inc.')
            ->assertJsonPath('found', true);
    }

    public function test_research_endpoints_work_without_a_configured_search_api(): void
    {
        config()->set('services.research.url', null);
        config()->set('services.research.key', null);

        Http::preventStrayRequests();
        Http::fake([
            'en.wikipedia.org/w/api.php*' => Http::response([
                'query' => ['search' => [[
                    'title' => 'Logitech Unifying Receiver',
                    'snippet' => 'Wireless receiver.',
                ]]],
            ], 200),
        ]);

        $this->postJson('/api/research/search', [
            'queries' => ['Logitech 046D C52B receiver'],
        ])->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('searches.0.source', 'wikipedia')
            ->assertJsonStructure(['available', 'message', 'results', 'searches']);

        $this->getJson('/api/research/software?vid=046D')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonStructure(['available', 'message', 'results', 'searches']);
    }

    public function test_ocr_endpoint_returns_structured_unavailable_result(): void
    {
        $result = [
            'available' => false,
            'extracted_text' => null,
            'fields' => ['fcc_id' => null, 'ic_id' => null, 'model' => null, 'serial' => null, 'vid' => null, 'pid' => null],
            'message' => 'OCR is not configured. Manual entry remains available.',
        ];

        $this->mock(ImageAnalysisService::class)
            ->shouldReceive('analyze')
            ->once()
            ->andReturn($result);

        $this->post('/api/identifications/ocr', [
            'photo' => UploadedFile::fake()->createWithContent('label.jpg', 'label'),
            'photo_type' => 'label',
        ])->assertOk()->assertJsonPath('available', false)->assertJsonPath('message', $result['message']);
    }
}
