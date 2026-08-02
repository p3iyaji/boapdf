<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

it('throttles repeated login attempts', function () {
    RateLimiter::clear('alan@example.com|127.0.0.1');

    User::factory()->create([
        'email' => 'alan@example.com',
        'password' => Hash::make('right-pass'),
    ]);

    for ($i = 0; $i < 5; $i++) {
        $this->post(route('authenticate'), [
            'email' => 'alan@example.com',
            'password' => 'wrong-pass',
        ]);
    }

    $this->post(route('authenticate'), [
        'email' => 'alan@example.com',
        'password' => 'wrong-pass',
    ])->assertStatus(429);
});
