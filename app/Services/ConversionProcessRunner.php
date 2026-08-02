<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class ConversionProcessRunner
{
    /**
     * @param  list<string>  $names
     * @param  list<string>  $extraDirectories
     */
    public function find(?string $configuredPath, array $names, array $extraDirectories = []): ?string
    {
        if (is_string($configuredPath) && $configuredPath !== '' && is_executable($configuredPath)) {
            return $configuredPath;
        }

        $directories = array_values(array_unique(array_merge(
            $extraDirectories,
            ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin', base_path('.venv/bin')],
        )));
        $finder = new ExecutableFinder;

        foreach ($names as $name) {
            $found = $finder->find($name, null, $directories);

            if ($found !== null && is_executable($found)) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $command
     */
    public function run(array $command, int $timeout, ?string $workingDirectory = null): string
    {
        $process = new Process(
            $command,
            $workingDirectory,
            ['PATH' => $this->executablePath()],
        );
        $process->setTimeout($timeout);
        $process->run();

        if (! $process->isSuccessful()) {
            $detail = trim($process->getErrorOutput().PHP_EOL.$process->getOutput());

            throw new RuntimeException(
                $detail !== ''
                    ? $detail
                    : 'The conversion command exited with status '.$process->getExitCode().'.',
            );
        }

        return trim($process->getOutput().PHP_EOL.$process->getErrorOutput());
    }

    private function executablePath(): string
    {
        $inheritedDirectories = explode(PATH_SEPARATOR, getenv('PATH') ?: '');

        return implode(PATH_SEPARATOR, array_values(array_unique(array_filter(array_merge(
            [
                base_path('.venv/bin'),
                '/opt/homebrew/bin',
                '/usr/local/bin',
                '/usr/bin',
                '/bin',
                '/usr/sbin',
                '/sbin',
            ],
            $inheritedDirectories,
        )))));
    }
}
