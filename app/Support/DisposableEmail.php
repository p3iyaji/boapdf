<?php

namespace App\Support;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class DisposableEmail
{
    /**
     * @var array<string, true>|null
     */
    private static ?array $domains = null;

    public static function isDisposable(?string $email): bool
    {
        if (! config('disposable-email.enabled', true)) {
            return false;
        }

        $domain = self::domainFrom($email);

        if ($domain === null) {
            return false;
        }

        if (self::isAllowed($domain)) {
            return false;
        }

        $blocked = self::domains();

        foreach (self::domainCandidates($domain) as $candidate) {
            if (isset($blocked[$candidate])) {
                return true;
            }
        }

        return false;
    }

    public static function flush(): void
    {
        self::$domains = null;
    }

    private static function domainFrom(?string $email): ?string
    {
        if ($email === null || $email === '' || ! str_contains($email, '@')) {
            return null;
        }

        $domain = Str::lower(Str::afterLast($email, '@'));

        return $domain !== '' ? $domain : null;
    }

    private static function isAllowed(string $domain): bool
    {
        /** @var list<string> $allow */
        $allow = config('disposable-email.allow', []);

        foreach (self::domainCandidates($domain) as $candidate) {
            if (in_array($candidate, $allow, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private static function domainCandidates(string $domain): array
    {
        $parts = explode('.', $domain);
        $candidates = [];

        while (count($parts) >= 2) {
            $candidates[] = implode('.', $parts);
            array_shift($parts);
        }

        return $candidates;
    }

    /**
     * @return array<string, true>
     */
    private static function domains(): array
    {
        if (self::$domains !== null) {
            return self::$domains;
        }

        $domains = [];

        $path = config('disposable-email.domains_path');

        if (is_string($path) && $path !== '' && File::isReadable($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

            foreach ($lines as $line) {
                $line = Str::lower(trim($line));

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                $domains[$line] = true;
            }
        }

        /** @var list<string> $deny */
        $deny = config('disposable-email.deny', []);

        foreach ($deny as $domain) {
            $domain = Str::lower(trim($domain));

            if ($domain !== '') {
                $domains[$domain] = true;
            }
        }

        return self::$domains = $domains;
    }
}
