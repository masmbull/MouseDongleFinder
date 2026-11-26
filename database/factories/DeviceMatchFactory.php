<?php

namespace Database\Factories;

use App\Models\DeviceMatch;
use App\Models\Dongle;
use App\Models\Mouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DeviceMatch>
 */
class DeviceMatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mouse_id' => Mouse::factory(),
            'dongle_id' => Dongle::factory(),
            'status' => $this->faker->randomElement(['matched', 'suspected', 'unmatched', 'unknown']),
            'notes' => $this->faker->sentence(),
            'matched_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'is_seed' => false,
        ];
    }
}
