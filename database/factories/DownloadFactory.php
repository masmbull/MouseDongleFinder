<?php

namespace Database\Factories;

use App\Models\Download;
use Illuminate\Database\Eloquent\Factories\Factory;

class DownloadFactory extends Factory
{
    protected $model = Download::class;

    public function definition(): array
    {
        return [
            'filename' => $this->faker->word().'.'.$this->faker->randomElement(['exe', 'zip', 'pdf']),
            'file_path' => $this->faker->filePath(),
            'source_url' => $this->faker->url(),
            'source_type' => $this->faker->randomElement(['official', 'third-party', 'unknown']),
            'manufacturer' => $this->faker->company(),
            'model' => $this->faker->word(),
            'software_type' => $this->faker->randomElement(['driver', 'pairing', 'firmware', 'manual', 'utility']),
            'file_size' => $this->faker->numberBetween(1000000, 50000000),
            'sha256_hash' => hash('sha256', $this->faker->text()),
            'download_status' => $this->faker->randomElement(['completed', 'failed', 'cancelled']),
            'downloaded_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'is_seed' => false,
        ];
    }
}
