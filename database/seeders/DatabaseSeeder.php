<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (! User::where('email', 'demo@boapdf.test')->exists()) {
            User::create([
                'name' => 'Demo User',
                'email' => 'demo@boapdf.test',
                'password' => Hash::make('password'),
            ]);
        }
    }
}
