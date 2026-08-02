<?php

declare(strict_types=1);

it('injects boa theme styles and mark on the login page', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee('id="boa-theme-vars"', false)
        ->assertSee('--boa-brand-600:', false)
        ->assertSee('boa-mark-', false);
});
