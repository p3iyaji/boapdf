<?php

namespace Database\Factories;

use App\Models\ConversionLog;
use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConversionLog>
 */
class ConversionLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'source_format' => 'pdf',
            'target_format' => $this->faker->randomElement(['docx', 'jpg', 'png', 'html', 'txt']),
            'processing_time_ms' => $this->faker->numberBetween(100, 30_000),
            'memory_usage_mb' => $this->faker->numberBetween(10, 512),
            'status' => 'success',
        ];
    }
}
