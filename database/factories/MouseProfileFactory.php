<?php

namespace Database\Factories;

use App\Models\MouseProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MouseProfile>
 */
class MouseProfileFactory extends Factory
{
    /**
     * Only real, documented VID:PID pairs are used here; unknown identifiers stay null.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $documentedReceiver = fake()->randomElement([
            ['vid' => '046D', 'pid' => 'C52B', 'receiver_family' => 'Unifying'],
            ['vid' => '046D', 'pid' => 'C548', 'receiver_family' => 'Bolt'],
            ['vid' => '1532', 'pid' => '007B', 'receiver_family' => 'HyperSpeed'],
            ['vid' => null, 'pid' => null, 'receiver_family' => null],
        ]);

        return [
            'brand' => fake()->randomElement(['Logitech', 'Razer', 'Microsoft', 'Corsair', 'SteelSeries']),
            'model' => 'Test Mouse '.fake()->unique()->numberBetween(1, 100000),
            'vid' => $documentedReceiver['vid'],
            'pid' => $documentedReceiver['pid'],
            'receiver_family' => $documentedReceiver['receiver_family'],
            'wireless_technology' => fake()->randomElement(['Proprietary 2.4 GHz', '2.4 GHz ISM (Bluetooth)']),
            'frequency' => '2.4 GHz ISM',
            'pairing_utility' => null,
            'official_support_url' => null,
            'notes' => 'Synthetic factory row for tests; not a researched product reference.',
            'source' => null,
        ];
    }
}
