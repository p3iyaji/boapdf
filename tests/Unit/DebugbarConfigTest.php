<?php

it('does not dump the full Symfony request in the debugbar', function () {
    expect(config('debugbar.collectors.symfony_request'))->toBeFalse();
});

it('excludes PDF routes from the debugbar', function () {
    expect(config('debugbar.except'))->toContain('pdf*');
});
