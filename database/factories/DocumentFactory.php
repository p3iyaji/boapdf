<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->slug(3).'.pdf';

        return [
            'user_id' => User::factory(),
            'original_name' => $name,
            'file_path' => 'uploads/'.$name,
            'file_size' => $this->faker->numberBetween(10_000, 5_000_000),
            'mime_type' => 'application/pdf',
            'pages' => $this->faker->numberBetween(1, 50),
            'status' => Document::STATUS_COMPLETED,
            'operation_type' => Document::OP_UPLOAD,
            'metadata' => [],
        ];
    }

    public function uploaded(): static
    {
        return $this->state(fn () => ['operation_type' => Document::OP_UPLOAD]);
    }

    public function merged(): static
    {
        return $this->state(fn () => ['operation_type' => Document::OP_MERGED]);
    }

    public function compressed(): static
    {
        return $this->state(fn () => ['operation_type' => Document::OP_COMPRESSED]);
    }

    public function signed(): static
    {
        return $this->state(fn () => ['operation_type' => Document::OP_SIGNED]);
    }

    public function captured(): static
    {
        return $this->state(fn () => ['operation_type' => Document::OP_CAPTURE]);
    }

    public function processing(): static
    {
        return $this->state(fn () => ['status' => Document::STATUS_PROCESSING]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => Document::STATUS_FAILED]);
    }
}
