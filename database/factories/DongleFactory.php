<?php

namespace Database\Factories;

use App\Models\Dongle;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class DongleFactory extends Factory
{
    protected $model = Dongle::class;

    public function definition(): array
    {
        $brands = ['Logitech', 'Razer', 'Microsoft', 'SteelSeries', 'Corsair', null];
        $types = ['Unifying', 'Nano', 'Lightspeed', 'Bolt', 'Bluetooth', 'Proprietary 2.4GHz', null];

        return [
            'dongle_id' => 'DG-'.strtoupper(Str::random(6)),
            'brand' => $this->faker->randomElement($brands),
            'model' => $this->faker->randomElement([null, $this->faker->words(2, true)]),
            'color' => $this->faker->randomElement(['Black', 'White', 'Grey', null]),
            'physical_description' => $this->faker->sentence(),
            'receiver_type' => $this->faker->randomElement($types),
            'vid' => $this->faker->randomElement(['046D', '1532', '045E', '1B1C', null]),
            'pid' => $this->faker->randomElement(['C52B', '0084', '0745', '1A02', null]),
            'notes' => $this->faker->sentence(),
            'is_seed' => false,
        ];
    }
}
