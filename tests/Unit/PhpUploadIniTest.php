<?php

use App\Support\PhpUploadIni;

it('builds php -d arguments for 128 MB uploads', function () {
    expect(PhpUploadIni::directives())->toMatchArray([
        'memory_limit' => '512M',
        'post_max_size' => '128M',
        'upload_max_filesize' => '128M',
        'max_execution_time' => '300',
        'max_input_time' => '300',
    ]);

    expect(PhpUploadIni::cliArguments())->toBe([
        '-d', 'memory_limit=512M',
        '-d', 'post_max_size=128M',
        '-d', 'upload_max_filesize=128M',
        '-d', 'max_execution_time=300',
        '-d', 'max_input_time=300',
    ]);
});

it('injects upload limits before the php built-in server flag', function () {
    $command = PhpUploadIni::applyToServerCommand(['php', '-S', '127.0.0.1:8000', 'server.php']);

    expect($command[0])->toBe('php')
        ->and($command)->toContain('post_max_size=128M')
        ->and($command)->toContain('upload_max_filesize=128M')
        ->and(array_search('-S', $command, true))->toBeGreaterThan(array_search('post_max_size=128M', $command, true));
});
