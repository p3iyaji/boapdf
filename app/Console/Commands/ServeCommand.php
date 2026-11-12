<?php

namespace App\Console\Commands;

use App\Support\PhpUploadIni;
use Illuminate\Foundation\Console\ServeCommand as BaseServeCommand;

class ServeCommand extends BaseServeCommand
{
    /**
     * Pass upload limits into the PHP built-in server. The parent `php artisan serve`
     * process does not inherit `-d` flags, and post_max_size cannot be changed later.
     *
     * @return list<string>
     */
    protected function serverCommand()
    {
        return PhpUploadIni::applyToServerCommand(parent::serverCommand());
    }
}
