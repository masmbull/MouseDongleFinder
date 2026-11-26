<?php

namespace Database\Factories;

use App\Models\MatchingSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MatchingSession>
 */
class MatchingSessionFactory extends Factory
{
    protected $model = MatchingSession::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Dongle Lab '.fake()->unique()->numberBetween(1, 100000),
            'status' => 'scanning',
            'dongles' => null,
            'observations' => null,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
