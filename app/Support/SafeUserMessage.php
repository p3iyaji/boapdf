<?php

namespace App\Support;

use Throwable;

class SafeUserMessage
{
    /**
     * Return a user-safe error string. Exception details stay in logs only.
     */
    public static function from(Throwable $e, string $fallback): string
    {
        return $fallback;
    }
}
