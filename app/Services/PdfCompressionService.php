<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

class PdfCompressionService
{
    /**
     * Ghostscript /PDFSETTINGS presets — smaller file → higher "/screen" aggression.
     *
     * @var array<string, string>
     */
    private const QUALITY_MAP = [
        'low' => 'prepress',
        'medium' => 'printer',
        'recommended' => 'ebook',
        'maximum' => 'screen',
    ];

    /**
     * Compress a PDF.
     *
     * Uses Ghostscript when available (`gs` on PATH or configured path). Unlike a
     * plain re-save, we disable JPEG/JPX passthrough so embedded images are decoded
     * and re-encoded (similar to consumer “compress PDF” tools). Optional qpdf
     * (11+) can further optimize images. Falls back to FPDI repacking when GS is
     * absent — that path rarely shrinks image-heavy files.
     *
     * @return array{path: string, original_size: int, new_size: int, method: string}
     */
    public function compress(string $inputPath, string $level = 'recommended'): array
    {
        if (! file_exists($inputPath)) {
            throw new RuntimeException("Source file not found: {$inputPath}");
        }

        if (! array_key_exists($level, self::QUALITY_MAP)) {
            $level = 'recommended';
        }

        $originalSize = filesize($inputPath) ?: 0;

        $gsBinary = $this->ghostscriptBinary();

        if ($gsBinary !== null) {
            try {
                $outputPath = $this->runGhostscriptPipeline($gsBinary, $inputPath, $level, $originalSize);

                return [
                    'path' => $outputPath,
                    'original_size' => $originalSize,
                    'new_size' => filesize($outputPath) ?: 0,
                    'method' => 'ghostscript',
                ];
            } catch (\Throwable $e) {
                Log::warning('Ghostscript compression failed, falling back to FPDI', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $outputPath = $this->compressWithFpdi($inputPath);

        return [
            'path' => $outputPath,
            'original_size' => $originalSize,
            'new_size' => filesize($outputPath) ?: 0,
            'method' => 'fpdi',
        ];
    }

    /**
     * Ghostscript pass, optional qpdf optimization, then one retry at maximum
     * aggression if the file did not shrink (common for “recommended” on scans).
     */
    private function runGhostscriptPipeline(string $gs, string $inputPath, string $level, int $originalSize): string
    {
        $path = $this->compressWithGhostscript($gs, $inputPath, $level);
        $path = $this->maybeQpdfOptimize($path);

        $newSize = filesize($path) ?: 0;

        if ($originalSize > 0 && $newSize >= $originalSize && $level !== 'maximum') {
            Log::info('PDF compression did not reduce size; retrying with maximum preset', [
                'level' => $level,
                'original_size' => $originalSize,
                'new_size' => $newSize,
            ]);

            @unlink($path);

            $path = $this->compressWithGhostscript($gs, $inputPath, 'maximum');
            $path = $this->maybeQpdfOptimize($path);
        }

        return $path;
    }

    private function compressWithGhostscript(string $gs, string $inputPath, string $level): string
    {
        $setting = self::QUALITY_MAP[$level];
        $outputPath = $this->ensureDirectory('compressed').'/'.Str::uuid().'.pdf';

        $argv = array_merge(
            [
                $gs,
                '-sDEVICE=pdfwrite',
                '-dCompatibilityLevel=1.5',
                '-dNOPAUSE',
                '-dQUIET',
                '-dBATCH',
                '-dSAFER',
                '-dCompressFonts=true',
                '-dSubsetFonts=true',
                '-dDetectDuplicateImages=true',
                '-dPDFSETTINGS=/'.$setting,
            ],
            $this->ghostscriptLevelArgs($level),
            [
                '-sOutputFile='.$outputPath,
                $inputPath,
            ],
        );

        $process = new Process($argv);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($outputPath) || filesize($outputPath) === 0) {
            $detail = trim($process->getErrorOutput().$process->getOutput());
            throw new RuntimeException(
                'Ghostscript compression failed'.($detail !== '' ? ': '.$detail : ' (exit '.$process->getExitCode().').')
            );
        }

        return $outputPath;
    }

    /**
     * Level-specific image handling. For non-print presets, disable JPEG/JPX
     * passthrough so GS actually re-encodes images (otherwise size barely moves).
     *
     * @return list<string>
     */
    private function ghostscriptLevelArgs(string $level): array
    {
        $noPassthrough = [
            '-dPassThroughJPEGImages=false',
            '-dPassThroughJPXImages=false',
        ];

        return match ($level) {
            'low' => [],
            'medium' => array_merge($noPassthrough, [
                '-dDownsampleColorImages=true',
                '-dDownsampleGrayImages=true',
                '-dDownsampleMonoImages=true',
                '-dColorImageDownsampleType=/Average',
                '-dGrayImageDownsampleType=/Average',
                '-dMonoImageDownsampleType=/Average',
                '-dColorImageResolution=200',
                '-dGrayImageResolution=200',
                '-dMonoImageResolution=300',
            ]),
            'recommended' => array_merge($noPassthrough, [
                '-dDownsampleColorImages=true',
                '-dDownsampleGrayImages=true',
                '-dDownsampleMonoImages=true',
                '-dColorImageDownsampleType=/Average',
                '-dGrayImageDownsampleType=/Average',
                '-dMonoImageDownsampleType=/Average',
                '-dColorImageResolution=96',
                '-dGrayImageResolution=96',
                '-dMonoImageResolution=120',
            ]),
            'maximum' => array_merge($noPassthrough, [
                '-dDownsampleColorImages=true',
                '-dDownsampleGrayImages=true',
                '-dDownsampleMonoImages=true',
                '-dColorImageDownsampleType=/Average',
                '-dGrayImageDownsampleType=/Average',
                '-dMonoImageDownsampleType=/Average',
                '-dColorImageResolution=72',
                '-dGrayImageResolution=72',
                '-dMonoImageResolution=96',
            ]),
            default => [],
        };
    }

    /**
     * Optional qpdf pass — further shrinks some PDFs when images can be re-encoded.
     */
    private function maybeQpdfOptimize(string $pdfPath): string
    {
        $qpdf = $this->qpdfBinary();
        if ($qpdf === null) {
            return $pdfPath;
        }

        $dir = dirname($pdfPath);
        $optimized = $dir.'/'.Str::uuid().'-opt.pdf';

        $process = new Process([$qpdf, '--optimize-images', $pdfPath, $optimized]);
        $process->setTimeout(600);
        $process->run();

        if (! $process->isSuccessful() || ! file_exists($optimized) || filesize($optimized) === 0) {
            @unlink($optimized);

            return $pdfPath;
        }

        $before = filesize($pdfPath) ?: 0;
        $after = filesize($optimized) ?: 0;

        if ($after < $before) {
            @unlink($pdfPath);

            return $optimized;
        }

        @unlink($optimized);

        return $pdfPath;
    }

    private function compressWithFpdi(string $inputPath): string
    {
        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($inputPath);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $template = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($template);
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($template);
        }

        $outputPath = $this->ensureDirectory('compressed').'/'.Str::uuid().'.pdf';
        $pdf->Output('F', $outputPath);

        return $outputPath;
    }

    private function ghostscriptBinary(): ?string
    {
        $configured = config('pdf.ghostscript_path') ?: env('GHOSTSCRIPT_PATH');
        if ($configured && is_executable($configured)) {
            return $configured;
        }

        $found = (new ExecutableFinder)->find('gs', null, ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin']);
        if ($found !== null && is_executable($found)) {
            return $found;
        }

        foreach (['/opt/homebrew/bin/gs', '/usr/local/bin/gs', '/usr/bin/gs'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function qpdfBinary(): ?string
    {
        $configured = config('pdf.qpdf_path');
        if (is_string($configured) && $configured !== '' && is_executable($configured)) {
            return $configured;
        }

        $found = (new ExecutableFinder)->find('qpdf', null, ['/opt/homebrew/bin', '/usr/local/bin', '/usr/bin']);
        if ($found !== null && is_executable($found)) {
            return $found;
        }

        foreach (['/opt/homebrew/bin/qpdf', '/usr/local/bin/qpdf', '/usr/bin/qpdf'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function ensureDirectory(string $subPath): string
    {
        $disk = DocumentsDisk::disk();
        if (! $disk->exists($subPath)) {
            $disk->makeDirectory($subPath);
        }

        return $disk->path($subPath);
    }
}
