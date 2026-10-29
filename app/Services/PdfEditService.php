<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class PdfEditService
{
    public function __construct(private ConversionProcessRunner $runner) {}

    /**
     * @param  array<string, mixed>  $spec
     * @return array{path: string, pages: int}
     */
    public function applyContent(string $sourcePath, array $spec): array
    {
        $this->assertImagesStayOnDisk($spec);

        return $this->run('apply', $sourcePath, $spec);
    }

    /**
     * @param  list<array<string, mixed>>  $pages
     * @return array{path: string, pages: int}
     */
    public function applyPages(string $sourcePath, array $pages): array
    {
        return $this->run('pages', $sourcePath, ['pages' => $pages]);
    }

    /**
     * @param  array<string, mixed>  $spec
     * @return array{path: string, pages: int}
     */
    private function run(string $command, string $sourcePath, array $spec): array
    {
        $python = $this->pythonBinary();

        if ($python === null) {
            throw new PdfEditException('PDF editing is not available on this server.');
        }

        $script = resource_path('conversion/pdf_edit.py');
        $outputPath = $this->outputPath();
        $specPath = $this->outputDirectory().'/spec-'.Str::uuid().'.json';
        file_put_contents($specPath, json_encode($spec, JSON_THROW_ON_ERROR));

        try {
            $output = $this->runner->run(
                [$python, $script, $command, $sourcePath, $outputPath, $specPath],
                (int) config('pdf.conversion.document_timeout', 120),
            );
        } catch (RuntimeException $exception) {
            if (is_file($outputPath)) {
                unlink($outputPath);
            }

            Log::warning('PDF edit command failed', ['output' => $exception->getMessage()]);

            throw new PdfEditException($this->userMessage($exception->getMessage()));
        } finally {
            if (is_file($specPath)) {
                unlink($specPath);
            }
        }

        $decoded = $this->decode($output);

        return [
            'path' => $outputPath,
            'pages' => (int) ($decoded['pages'] ?? 0),
        ];
    }

    private function pythonBinary(): ?string
    {
        $configured = config('pdf.conversion.python_path');

        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $venvPython = base_path('.venv/bin/python');

        if (is_executable($venvPython)) {
            return $venvPython;
        }

        return $this->runner->find(null, ['python3', 'python'], [base_path('.venv/bin')]);
    }

    /**
     * @param  array<string, mixed>  $spec
     */
    private function assertImagesStayOnDisk(array $spec): void
    {
        $root = rtrim(realpath(DocumentsDisk::disk()->path('')) ?: DocumentsDisk::disk()->path(''), DIRECTORY_SEPARATOR);

        foreach ($spec['images'] ?? [] as $image) {
            $path = (string) ($image['path'] ?? '');
            $resolved = realpath($path) ?: '';

            if ($path === '' || $resolved === '' || ! str_starts_with($resolved, $root.DIRECTORY_SEPARATOR)) {
                throw new PdfEditException('Could not edit this PDF.');
            }
        }
    }

    private function userMessage(string $output): string
    {
        $decoded = $this->decode($output);
        $message = $decoded['error'] ?? null;

        if (! is_string($message) || $message === '') {
            return 'Could not edit this PDF.';
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(string $output): array
    {
        if (preg_match('/\{.*\}/s', $output, $matches) !== 1) {
            return [];
        }

        $decoded = json_decode($matches[0], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function outputPath(): string
    {
        return $this->outputDirectory().'/'.Str::uuid().'.pdf';
    }

    private function outputDirectory(): string
    {
        $disk = DocumentsDisk::disk();

        if (! $disk->exists('edited')) {
            $disk->makeDirectory('edited');
        }

        return $disk->path('edited');
    }
}
