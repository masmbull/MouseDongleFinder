<?php

namespace Database\Factories;

use App\Models\UsbScan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class UsbScanFactory extends Factory
{
    protected $model = UsbScan::class;

    public function definition(): array
    {
        return [
            'scan_id' => 'SCN-'.strtoupper(Str::random(8)),
            'devices' => [],
            'device_count' => 0,
            'scanned_at' => now(),
            'scanned_by' => $this->faker->userName(),
            'is_seed' => false,
        ];
    }
}
