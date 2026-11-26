<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Research never invents results: fakes supply the only simulated API responses. */
class ResearchEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.research.url', null);
        config()->set('services.research.key', null);
    }

    public function test_search_without_configuration_uses_keyless_wikipedia_search_and_official_links(): void
    {
        Http::preventStrayRequests();
        Http::fake([
            'en.wikipedia.org/w/api.php*' => Http::response([
                'query' => ['search' => [[
                    'title' => 'Logitech Unifying Receiver',
                    'snippet' => 'A wireless receiver from Logitech.',
                ]]],
            ], 200),
        ]);

        $response = $this->postJson('/api/research/search', [
            'queries' => ['Logitech 046D C52B receiver'],
            'vid' => '046D',
            'pid' => 'C52B',
        ])->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('message', null)
            ->assertJsonPath('searches.0.source', 'wikipedia')
            ->assertJsonStructure(['available', 'message', 'results', 'searches']);

        $this->assertNotEmpty($response->json('results'));
        $this->assertSame('ok', $response->json('searches.0.status'));
        $urls = array_column($response->json('results'), 'url');
        $this->assertContains('https://en.wikipedia.org/wiki/Logitech_Unifying_Receiver', $urls);

        $urls = array_column($response->json('results'), 'url');
        $this->assertContains('https://support.logi.com/', $urls);
        $this->assertContains('https://www.linux-usb.org/usb.ids', $urls);
        $this->assertContains('https://devicehunt.com/view/type/usb/vendor/046D/device/C52B', $urls);

        foreach ($response->json('results') as $result) {
            $this->assertNotSame('', $result['url']);
            $this->assertNotFalse(filter_var($result['url'], FILTER_VALIDATE_URL));
        }
    }

    public function test_search_with_configured_api_uses_faked_response(): void
    {
        config()->set('services.research.url', 'https://search.example.com/api');
        config()->set('services.research.key', 'test-key');

        Http::preventStrayRequests();
        Http::fake([
            'search.example.com/api' => Http::response([
                'results' => [[
                    'title' => 'Real indexed result',
                    'url' => 'https://example.org/real-result',
                    'snippet' => 'Returned by the configured API.',
                ]],
            ], 200),
        ]);

        $response = $this->postJson('/api/research/search', [
            'query' => 'Logitech C52B manual',
        ])->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('searches.0.status', 'ok');

        $this->assertContains('https://example.org/real-result', array_column($response->json('results'), 'url'));
        Http::assertSentCount(1);
    }

    public function test_search_requires_a_query(): void
    {
        $this->postJson('/api/research/search', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['query', 'queries']);
    }

    public function test_software_returns_official_results_for_a_known_vendor(): void
    {
        Http::preventStrayRequests();

        $response = $this->getJson('/api/research/software?vid=046D')
            ->assertOk()
            ->assertJsonPath('available', true)
            ->assertJsonPath('message', null)
            ->assertJsonStructure(['available', 'message', 'results', 'searches']);

        $names = array_column($response->json('results'), 'name');
        $this->assertContains('Logi Options+', $names);
        $this->assertContains('Logitech Unifying Software', $names);

        foreach ($response->json('results') as $result) {
            if (($result['name'] ?? null) === 'Logi Options+') {
                $this->assertTrue($result['official']);
                $this->assertSame('https://support.logi.com/', $result['url']);
            }
        }
    }

    public function test_software_for_unknown_vendor_only_returns_real_reference_links(): void
    {
        $response = $this->getJson('/api/research/software?vid=9999')
            ->assertOk()
            ->assertJsonPath('available', true);

        $this->assertNotEmpty($response->json('results'));
        foreach ($response->json('results') as $result) {
            $this->assertNotFalse(filter_var($result['url'], FILTER_VALIDATE_URL));
        }
    }
}
