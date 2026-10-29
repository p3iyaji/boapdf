<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disposable email blocking
    |--------------------------------------------------------------------------
    |
    | When enabled, registration, login, profile updates, and authenticated
    | access reject addresses whose domain appears on the blocklist.
    |
    */

    'enabled' => (bool) env('DISPOSABLE_EMAIL_BLOCKING', true),

    /*
    | One domain per line. Sourced from the disposable-email-domains project.
    */
    'domains_path' => resource_path('data/disposable_email_domains.txt'),

    /*
    | Domains that should never be treated as disposable (false-positive overrides).
    */
    'allow' => [
        // 'company-alias.com',
    ],

    /*
    | Extra domains to block beyond the file (local additions).
    */
    'deny' => [
        // 'known-throwaway.test',
    ],

];
