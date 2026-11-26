<?php

namespace Tests\Feature;

use App\Models\DeviceMatch;
use App\Models\DevicePhoto;
use App\Models\Dongle;
use App\Models\Identification;
use App\Models\Mouse;
use App\Models\MouseProfile;
use App\Models\UsbProduct;
use App\Models\UsbVendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseSeederSampleDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_seeder_produces_the_global_catalogue_without_breaking_match_invariants(): void
    {
        $this->seed();
        $this->seed();

        $this->assertGreaterThanOrEqual(45, MouseProfile::distinct()->count('brand'));
        $this->assertGreaterThanOrEqual(80, MouseProfile::count());
        $this->assertGreaterThanOrEqual(60, Mouse::count());
        $this->assertGreaterThanOrEqual(50, Dongle::count());
        $this->assertGreaterThanOrEqual(50, Identification::count());
        $this->assertGreaterThanOrEqual(3000, UsbVendor::count());
        $this->assertGreaterThanOrEqual(2500, UsbProduct::count());

        $this->assertSame(0, Mouse::where('is_seed', false)->count());
        $this->assertSame(0, Dongle::where('is_seed', false)->count());
        $this->assertSame(0, Identification::where('is_seed', false)->count());
        $this->assertSame(0, DeviceMatch::where('is_seed', false)->count());

        $profileBrands = MouseProfile::distinct()->orderBy('brand')->pluck('brand')->all();
        $mouseBrands = Mouse::distinct()->orderBy('brand')->pluck('brand')->all();
        $dongleBrands = Dongle::distinct()->orderBy('brand')->pluck('brand')->all();

        $this->assertEmpty(array_diff($profileBrands, $mouseBrands), 'Every profile brand must have a sample mouse.');
        $this->assertEmpty(array_diff($profileBrands, $dongleBrands), 'Every profile brand must have a sample dongle.');

        $this->assertSame(0, Identification::whereNotNull('wireless_technology')
            ->whereNull('frequency')->count());
        $this->assertSame(0, Identification::where('confidence', 'high')
            ->where(static function ($query): void {
                $query->whereNull('vid')->orWhereNull('pid');
            })->count());
        $this->assertSame(0, Identification::whereNotNull('vid')
            ->whereNotNull('pid')
            ->where('device_category', '!=', 'wireless_mouse_receiver')
            ->count());

        foreach (Identification::query()->cursor() as $identification) {
            $sources = $identification->research_sources ?? [];
            $this->assertNotEmpty($sources, 'Every identification must carry real research sources.');
            foreach ($sources as $source) {
                $this->assertNotEmpty($source['url'] ?? null);
                $this->assertMatchesRegularExpression('#^https?://#', $source['url']);
            }
        }

        foreach (['mouse_id', 'dongle_id'] as $column) {
            $duplicates = DB::table('device_matches')
                ->whereIn('status', ['matched', 'suspected'])
                ->select($column)
                ->groupBy($column)
                ->havingRaw('COUNT(*) > 1')
                ->pluck($column);

            $this->assertCount(0, $duplicates, 'A device must not have two active matches.');
        }

        $this->assertSame(0, Dongle::whereNull('brand')
            ->whereHas('identification')
            ->count(), 'Genuinely unknown dongles must not be given an identity.');

        $this->assertSeedReferencePhotos();
    }

    /**
     * Coverage is deliberately conservative: only devices with a real, exact-model photo found in
     * Wikimedia Commons or an official manufacturer gallery are listed in the manifest. Absence is
     * honest and is printed by the seeder rather than filled with a placeholder or a lookalike image.
     */
    private function assertSeedReferencePhotos(): void
    {
        $this->assertGreaterThanOrEqual(60, DevicePhoto::count());

        $perDevice = DevicePhoto::query()
            ->select('photoable_type', 'photoable_id')
            ->selectRaw('COUNT(*) as photo_count')
            ->groupBy('photoable_type', 'photoable_id')
            ->pluck('photo_count');

        $this->assertGreaterThanOrEqual(17, $perDevice->count(), 'At least 17 seeded devices must have a reference photo.');
        $this->assertGreaterThanOrEqual(13, $perDevice->filter(static fn (int $count): bool => $count >= 2)->count());

        foreach (DevicePhoto::query()->cursor() as $photo) {
            $this->assertTrue($photo->is_seed);
            $this->assertNotEmpty($photo->path);
            $this->assertFileExists(public_path($photo->path));
            $this->assertNotEmpty($photo->source_url);
            $this->assertMatchesRegularExpression('#^https?://#', $photo->source_url);
            $this->assertNotEmpty($photo->source_name);
            $this->assertContains($photo->photo_type, ['top', 'underside', 'receiver', 'marking', 'side', 'detail']);
            $this->assertSame(
                asset($photo->path),
                $photo->url,
                'The url accessor must resolve the locally stored photo.',
            );
        }
    }
}
