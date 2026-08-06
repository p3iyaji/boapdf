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
            'source_document_id' => null,
            'requester_email' => $this->faker->safeEmail(),
            'signer_email' => $this->faker->safeEmail(),
            'signer_name' => $this->faker->name(),
            'token' => null,
            'signature_position' => [
                'x' => 50,
                'y' => 700,
                'page' => 1,
                'width' => 150,
                'height' => 50,
            ],
            'status' => SignatureRequest::STATUS_PENDING,
            'sort_order' => 0,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SignatureRequest $request): void {
            if ($request->source_document_id === null && $request->document_id !== null) {
                $request->source_document_id = $request->document_id;
            }
        })->afterCreating(function (SignatureRequest $request): void {
            if ($request->source_document_id === null && $request->document_id !== null) {
                $request->forceFill(['source_document_id' => $request->document_id])->saveQuietly();
            }
        });
    }

    public function pendingInvite(): static
    {
        return $this->state(fn (): array => [
            'status' => SignatureRequest::STATUS_PENDING,
            'token' => SignatureRequest::generateToken(),
            'signature_position' => null,
            'signed_file_path' => null,
            'signed_at' => null,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function signed(): static
    {
        return $this->state(fn (): array => [
            'status' => SignatureRequest::STATUS_SIGNED,
            'token' => null,
            'signed_at' => now(),
        ]);
    }
}
