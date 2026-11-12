<?php

it('raises the PHP memory limit to at least 512 MB', function () {
    require base_path('bootstrap/php-runtime.php');

    $limit = (string) ini_get('memory_limit');

    if ($limit === '-1') {
        expect($limit)->toBe('-1');

        return;
    }

    $unit = strtolower(substr($limit, -1));
    $number = (int) $limit;
    $bytes = match ($unit) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => (int) $limit,
    };

    expect($bytes)->toBeGreaterThanOrEqual(512 * 1024 * 1024);
});
