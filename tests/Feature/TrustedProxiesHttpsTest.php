<?php

it('treats x-forwarded-proto https as a secure request', function () {
    $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'boapdf.com',
    ])->get('/');

    expect(request()->secure())->toBeTrue();
    expect(asset('build/assets/app.css'))->toStartWith('https://');
});

it('renders https asset and form urls behind a tls-terminating proxy', function () {
    $this->withHeaders([
        'X-Forwarded-Proto' => 'https',
        'X-Forwarded-Host' => 'boapdf.com',
        'Host' => 'boapdf.com',
    ])->get('/')
        ->assertOk()
        ->assertDontSee('http://boapdf.com/build/', false)
        ->assertDontSee('action="http://boapdf.com/', false);
});
