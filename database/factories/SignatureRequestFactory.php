<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\SignatureRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SignatureRequest>
 */
class SignatureRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'requester_email' => $this->faker->safeEmail(),
            'signer_email' => $this->faker->safeEmail(),
            'signature_position' => [
                'x' => 50,
                'y' => 700,
                'page' => 1,
                'width' => 150,
                'height' => 50,
            ],
            'status' => SignatureRequest::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ];
    }
}
