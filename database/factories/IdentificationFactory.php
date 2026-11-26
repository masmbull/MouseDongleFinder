<?php

namespace Database\Factories;

use App\Models\Dongle;
use App\Models\Identification;
use Illuminate\Database\Eloquent\Factories\Factory;

class IdentificationFactory extends Factory
{
    protected $model = Identification::class;

    public function definition(): array
    {
        return [
            'dongle_id' => Dongle::factory(),
            'vid' => $this->faker->randomElement(['046D', '1532', '045E']),
            'pid' => $this->faker->randomElement(['C52B', '0084', '0745']),
            'probable_manufacturer' => $this->faker->randomElement(['Logitech', 'Razer', 'Microsoft']),
            'probable_model' => $this->faker->words(2, true),
            'device_category' => $this->faker->randomElement(['USB Receiver', 'Wireless Dongle', 'Bluetooth Adapter']),
            'wireless_technology' => $this->faker->randomElement(['2.4GHz Proprietary', 'Bluetooth', 'BLE']),
            'frequency' => $this->faker->randomElement(['2.4 GHz', '2.402-2.480 GHz', null]),
            'receiver_family' => $this->faker->randomElement(['Unifying', 'Nano', 'Lightspeed', 'Bolt', null]),
            'compatible_mice' => [$this->faker->words(2, true), $this->faker->words(2, true)],
            'pairing_utility' => $this->faker->randomElement(['Logitech Unifying Software', 'Razer Synapse', null]),
            'official_support_url' => $this->faker->url(),
            'evidence' => ['VID/PID match in USB database', 'Manufacturer identification from vendor ID'],
            'confidence' => $this->faker->randomElement(['high', 'medium', 'low', 'unknown']),
            'research_sources' => [$this->faker->url()],
            'notes' => $this->faker->sentence(),
            'is_seed' => false,
        ];
    }
}
