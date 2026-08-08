<?php

it('publishes the terms of use at /terms', function () {
    $this->get(route('legal.terms'))
        ->assertOk()
        ->assertSee('Terms of Use', false)
        ->assertSee('free of charge', false)
        ->assertSee('students', false)
        ->assertSee(config('app.name'), false);
});

it('publishes the privacy policy at /privacy', function () {
    $this->get(route('legal.privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy', false)
        ->assertSee('We do not sell', false)
        ->assertSee(config('legal.contact_email'), false);
});

it('links legal pages from the homepage footer', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.privacy'), false);
});

it('links legal pages from login and register', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.privacy'), false);

    $this->get(route('register'))
        ->assertOk()
        ->assertSee(route('legal.terms'), false)
        ->assertSee(route('legal.privacy'), false)
        ->assertSee('name="terms"', false);
});
