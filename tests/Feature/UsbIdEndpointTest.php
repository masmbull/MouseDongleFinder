<?php

namespace Tests\Feature;

use App\Models\UsbProduct;
use App\Models\UsbVendor;
use App\Services\UsbIdDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** VID/PID lookup is local-first; refresh is always faked so no network is used. */
class UsbIdEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_lookup_returns_known_vendor_and_product_with_uppercase_normalisation(): void
    {
        UsbVendor::query()->create(['vid' => '046D', 'vendor_name' => 'Logitech, Inc.']);
        UsbProduct::query()->create(['vid' => '046D', 'pid' => 'C52B', 'product_name' => 'USB Receiver (Unifying)']);

        $this->getJson('/api/usb-ids/lookup?vid=046d&pid=c52b')
            ->assertOk()
            ->assertJsonPath('vid', '046D')
            ->assertJsonPath('pid', 'C52B')
            ->assertJsonPath('vendor_name', 'Logitech, Inc.')
            ->assertJsonPath('product_name', 'USB Receiver (Unifying)')
            ->assertJsonPath('found', true);
    }

    public function test_lookup_of_unknown_identifiers_reports_found_false_without_guessing(): void
    {
        $this->getJson('/api/usb-ids/lookup?vid=9999&pid=8888')
            ->assertOk()
            ->assertJsonPath('vid', '9999')
            ->assertJsonPath('pid', '8888')
            ->assertJsonPath('vendor_name', null)
            ->assertJsonPath('product_name', null)
            ->assertJsonPath('found', false);
    }

    public function test_lookup_rejects_malformed_identifiers(): void
    {
        $this->getJson('/api/usb-ids/lookup?vid=04')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('vid');
    }

    public function test_refresh_imports_mocked_usb_ids_and_records_last_updated(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake([
            'www.linux-usb.org/*' => Http::response(
                "# community data\n046D  Logitech, Inc.\n\tC52B  USB Receiver (Unifying)\n\t\t01  Interface\n",
                200,
            ),
        ]);

        $response = $this->postJson('/api/usb-ids/refresh')
            ->assertOk()
            ->assertJsonPath('error', null)
            ->assertJsonPath('imported.vendors', 1)
            ->assertJsonPath('imported.products', 1);

        $lastUpdated = $response->json('last_updated');
        $this->assertIsString($lastUpdated);
        Storage::disk('local')->assertExists('usbids/usb.ids');
        $this->assertSame($lastUpdated, Cache::get(UsbIdDatabase::CACHE_KEY));
        $this->assertDatabaseHas('usb_vendors', ['vid' => '046D', 'vendor_name' => 'Logitech, Inc.']);
        $this->assertDatabaseHas('usb_products', [
            'vid' => '046D',
            'pid' => 'C52B',
            'product_name' => 'USB Receiver (Unifying)',
        ]);
    }

    public function test_refresh_failure_reports_error_and_keeps_offline_rows_available(): void
    {
        Storage::fake('local');
        Http::preventStrayRequests();
        Http::fake(['www.linux-usb.org/*' => Http::response('service unavailable', 503)]);

        $response = $this->postJson('/api/usb-ids/refresh')
            ->assertOk()
            ->assertJsonPath('imported.vendors', 0)
            ->assertJsonPath('imported.products', 0)
            ->assertJsonPath('message', 'Offline curated VID/PID rows remain available.');

        $this->assertStringContainsString('Could not refresh usb.ids', $response->json('error'));
    }
}
