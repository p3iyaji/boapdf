<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        User::withTrashed()->updateOrCreate(
            ['email' => 'admin@boapdf.com'],
            [
                'name' => 'BOA PDF Admin',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'is_active' => true,
                'deleted_at' => null,
                'email_verified_at' => now(),
            ],
        );
    }
}
