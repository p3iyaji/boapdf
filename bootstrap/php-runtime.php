<?php

/**
 * PHP CLI (`php artisan serve`) ignores public/.user.ini. Raise limits here so
 * 100 MB PDF uploads do not fatal with a 128 MB memory_limit.
 *
 * post_max_size / upload_max_filesize cannot be changed here (PHP_INI_PERDIR).
 * Those are passed as `php -d` flags by App\Console\Commands\ServeCommand.
 */
$memoryLimit = (string) ini_get('memory_limit');

if ($memoryLimit !== '-1') {
    $unit = strtolower(substr($memoryLimit, -1));
    $number = (int) $memoryLimit;
    $bytes = match ($unit) {
        'g' => $number * 1024 ** 3,
        'm' => $number * 1024 ** 2,
        'k' => $number * 1024,
        default => (int) $memoryLimit,
    };

    if ($bytes < 512 * 1024 * 1024) {
        ini_set('memory_limit', '512M');
    }
}

$executionTime = (int) ini_get('max_execution_time');

if ($executionTime > 0 && $executionTime < 300) {
    ini_set('max_execution_time', '300');
}

$inputTime = (int) ini_get('max_input_time');

if ($inputTime > 0 && $inputTime < 300) {
    ini_set('max_input_time', '300');
}
