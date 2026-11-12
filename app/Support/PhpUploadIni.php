<?php

namespace App\Support;

class PhpUploadIni
{
    /**
     * CLI-only PHP directives for large PDF uploads.
     *
     * post_max_size and upload_max_filesize are PHP_INI_PERDIR and cannot be
     * raised with ini_set() after startup (artisan serve ignores .user.ini).
     *
     * @return array<string, string>
     */
    public static function directives(): array
    {
        return [
            'memory_limit' => (string) config('pdf.php_memory_limit', '512M'),
            'post_max_size' => (string) config('pdf.php_post_max_size', '128M'),
            'upload_max_filesize' => (string) config('pdf.php_upload_max_filesize', '128M'),
            'max_execution_time' => (string) config('pdf.php_max_execution_time', '300'),
            'max_input_time' => (string) config('pdf.php_max_input_time', '300'),
        ];
    }

    /**
     * @return list<string>
     */
    public static function cliArguments(): array
    {
        $arguments = [];

        foreach (self::directives() as $key => $value) {
            $arguments[] = '-d';
            $arguments[] = $key.'='.$value;
        }

        return $arguments;
    }

    /**
     * @param  list<string>  $command
     * @return list<string>
     */
    public static function applyToServerCommand(array $command): array
    {
        $binary = array_shift($command);

        return [
            $binary,
            ...self::cliArguments(),
            ...$command,
        ];
    }
}
