<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;

class DocumentsDisk
{
    public static function name(): string
    {
        return (string) config('filesystems.documents', config('filesystems.default', 'local'));
    }

    public static function disk(): Filesystem
    {
        return Storage::disk(self::name());
    }
}
