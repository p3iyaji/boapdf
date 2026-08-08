<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory();

        return [
            'user_id' => $user,
            'actor_name' => fake()->name(),
            'actor_email' => fake()->safeEmail(),
            'action' => fake()->randomElement([
                'auth.login',
                'auth.logout',
                'profile.updated',
                'password.changed',
                'account.deleted',
                'document.uploaded',
                'document.deleted',
            ]),
            'description' => fake()->sentence(),
            'metadata' => ['source' => 'factory'],
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
