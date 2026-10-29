<?php

declare(strict_types=1);

use App\Models\User;

it('keeps the mobile header mark inside the top bar', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertSee('overflow-hidden', false)
        ->assertSee('h-8 w-8 shrink-0 drop-shadow-md', false);
});

it('injects boa theme styles and mark on the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('id="boa-theme-vars"', false)
        ->assertSee('--boa-brand-600:', false)
        ->assertSee('boa-mark-', false);
});
