<?php

namespace Database\Factories;

use App\Models\Mouse;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MouseFactory extends Factory
{
    protected $model = Mouse::class;

    public function definition(): array
    {
        $brands = ['Logitech', 'Razer', 'Microsoft', 'SteelSeries', 'Corsair'];
        $colors = ['Black', 'White', 'Grey', 'Blue', 'RGB'];

        return [
            'mouse_id' => 'MS-'.strtoupper(Str::random(6)),
            'brand' => $this->faker->randomElement($brands),
            'model' => $this->faker->words(2, true),
            'color' => $this->faker->randomElement($colors),
            'physical_description' => $this->faker->sentence(),
            'connectivity_type' => $this->faker->randomElement(['2.4GHz wireless', 'Bluetooth', 'Wired', 'Dual mode']),
            'notes' => $this->faker->sentence(),
            'is_seed' => false,
        ];
    }
}
