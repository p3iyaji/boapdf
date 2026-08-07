<?php

use App\Models\User;

it('renders the homepage with brand intro and subtle boa atmosphere', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(config('app.name'), false)
        ->assertSee('Hold your documents steady', false)
        ->assertSee('What you can do', false)
        ->assertSee('images/boa-constrictor.jpg', false)
        ->assertSee('boa-mark-', false)
        ->assertSee(route('register'), false)
        ->assertSee(route('login'), false);
});

it('links authenticated visitors to the dashboard from the homepage', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('home'))
        ->assertOk()
        ->assertSee(route('dashboard'), false)
        ->assertSee('Open dashboard', false);
});

it('links back to the homepage from login and register', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('home'), false)
        ->assertSee('Back to home', false);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee(route('home'), false)
        ->assertSee('Back to home', false);
});
