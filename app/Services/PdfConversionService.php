<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use DOMDocument;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

class PdfConversionService
{
    public const SUPPORTED_TARGETS = ['docx', 'doc', 'jpg', 'jpeg', 'png', 'html', 'txt'];

    public function __construct(private ConversionProcessRunner $processRunner) {}

    /**
     * @return array{
     *     path: string,
     *     pages: int,
     *     extension: string,
     *     mime_type: string,
     *     files: list<string>
     * }
     */
    public function convertFromPdf(string $pdfPath, string $target, ?string $password = null): array
    {
        $target = strtolower($target);

        if (! in_array($target, self::SUPPORTED_TARGETS, true)) {
            throw new PdfConversionException("Unsupported target format: {$target}.");
        }

        if (! file_exists($pdfPath)) {
            throw new PdfConversionException('The source PDF could not be found.');
        }

        $workspace = $this->makeWorkspace();

        try {
            $requiresEditableText = in_array($target, ['docx', 'doc', 'html', 'txt'], true);
            [$preparedPdf, $pageCount] = $this->preparePdf(
                $pdfPath,
                $password,
                $workspace,
                $requiresEditableText,
            );

            return match ($target) {
                'docx' => $this->toDocx($preparedPdf, $pageCount, $workspace),
                'doc' => $this->toDoc($preparedPdf, $pageCount, $workspace),
                'html' => $this->toHtml($preparedPdf, $pageCount, $workspace),
                'txt' => $this->toText($preparedPdf, $pageCount, $workspace),
                'jpg', 'jpeg', 'png' => $this->toImages($preparedPdf, $target, $pageCount, $workspace),
            };
        } finally {
            File::deleteDirectory($workspace);
        }
    }

    public function countPages(string $pdfPath, ?string $password = null): int
    {
        $workspace = $this->makeWorkspace();

        try {
            $preparedPdf = $this->preparePdf($pdfPath, $password, $workspace, false);

            return (int) $preparedPdf[1];
        } finally {
            File::deleteDirectory($workspace);
        }
    }

    /**
     * @return array{0: string, 1: int}
     */
    private function preparePdf(
        string $pdfPath,
        ?string $password,
        string $workspace,
        bool $applyOcr = true,
    ): array {
        $password = is_string($password) && $password !== '' ? $password : null;

        try {
            $pageCount = $this->inspectPageCount($pdfPath);
            $preparedPdf = $pdfPath;
        } catch (Throwable $inspectionFailure) {
            if ($inspectionFailure->getCode() === PdfConversionException::MISSING_TOOL) {
                throw $inspectionFailure;
            }

            if ($password === null) {
                throw new PdfConversionException(
                    'The PDF is invalid, damaged, or encrypted. Supply its password and try again.',
                    previous: $inspectionFailure,
                );
            }

            $preparedPdf = $this->decryptPdf($pdfPath, $password, $workspace);
            $pageCount = $this->inspectPageCount($preparedPdf);
        }

        if (! $applyOcr) {
            return [$preparedPdf, $pageCount];
        }

        if ($this->pdfHasExtractableText($preparedPdf)) {
            return [$preparedPdf, $pageCount];
        }

        return [$this->applyOcr($preparedPdf, $workspace), $pageCount];
    }

    private function inspectPageCount(string $pdfPath): int
    {
        $pdfInfo = $this->requiredTool('pdfinfo_path', ['pdfinfo'], 'inspect PDF files');

        try {
            $output = $this->processRunner->run(
                [$pdfInfo, $pdfPath],
                $this->timeout(),
            );
        } catch (Throwable $exception) {
            throw PdfConversionException::failed('PDF inspection', $exception);
        }

        if (preg_match('/^Pages:\s+(\d+)$/mi', $output, $matches) !== 1) {
            throw new PdfConversionException('PDF inspection did not return a valid page count.');
        }

        $pageCount = (int) $matches[1];

        if ($pageCount < 1) {
            throw new PdfConversionException('The PDF does not contain any pages.');
        }

        return $pageCount;
    }

    private function decryptPdf(string $pdfPath, string $password, string $workspace): string
    {
        $qpdf = $this->requiredTool('qpdf_path', ['qpdf'], 'open encrypted PDF files');
        $passwordFile = $workspace.'/password.txt';
        $outputPath = $workspace.'/decrypted.pdf';

        File::put($passwordFile, $password);
        chmod($passwordFile, 0600);

        try {
            $this->processRunner->run(
                [
                    $qpdf,
                    '--password-file='.$passwordFile,
                    '--decrypt',
                    '--',
                    $pdfPath,
                    $outputPath,
                ],
                $this->timeout(),
            );
        } catch (Throwable $exception) {
            throw new PdfConversionException(
                'The PDF could not be decrypted. Verify that the password is correct.',
                previous: $exception,
            );
        } finally {
            File::delete($passwordFile);
        }

        $this->assertOutput($outputPath, 'PDF decryption');

        return $outputPath;
    }

    private function pdfHasExtractableText(string $pdfPath): bool
    {
        $pdfToText = $this->processRunner->find(
            config('pdf.conversion.pdftotext_path'),
            ['pdftotext'],
        );

        if ($pdfToText === null) {
            return false;
        }

        try {
            $extracted = $this->processRunner->run(
                [$pdfToText, '-enc', 'UTF-8', $pdfPath, '-'],
                $this->timeout(),
            );
        } catch (Throwable) {
            return false;
        }

        $compact = preg_replace('/\s+/u', '', $extracted) ?? '';
        $minimum = max(1, (int) config('pdf.conversion.min_extractable_chars', 20));

        return mb_strlen($compact) >= $minimum;
    }

    private function applyOcr(string $pdfPath, string $workspace): string
    {
        if (! config('pdf.conversion.ocr_enabled', true)) {
            return $pdfPath;
        }

        $ocrMyPdf = $this->processRunner->find(
            config('pdf.conversion.ocrmypdf_path'),
            ['ocrmypdf'],
        );

        if ($ocrMyPdf === null) {
            Log::warning('PDF conversion skipped OCR because ocrmypdf is unavailable.', [
                'pdf' => basename($pdfPath),
            ]);

            return $pdfPath;
        }

        $outputPath = $workspace.'/searchable.pdf';
        $command = [
            $ocrMyPdf,
            '--skip-text',
            '--invalidate-digital-signatures',
            '--output-type',
            'pdf',
            '--optimize',
            '0',
            '--jobs',
            (string) config('pdf.conversion.ocr_jobs', 2),
        ];
        $language = trim((string) config('pdf.conversion.ocr_language', 'eng'));

        if ($language !== '') {
            $command[] = '--language';
            $command[] = $language;
        }

        $command[] = $pdfPath;
        $command[] = $outputPath;

        try {
            $this->processRunner->run($command, $this->ocrTimeout());
            $this->assertOutput($outputPath, 'optical character recognition');

            return $outputPath;
        } catch (Throwable $exception) {
            Log::warning('PDF conversion continued without OCR after OCR failure.', [
                'pdf' => basename($pdfPath),
                'error' => $exception->getMessage(),
            ]);

            return $pdfPath;
        }
    }

    /**
     * @return array{
     *     path: string,
     *     pages: int,
     *     extension: string,
     *     mime_type: string,
     *     files: list<string>
     * }
     */
    private function toDocx(string $pdfPath, int $pageCount, string $workspace): array
    {
        $outputPath = $this->moveToConverted(
            $this->toDocxWorkspace($pdfPath, $workspace),
            'docx',
        );

        return $this->result(
            $outputPath,
            $pageCount,
            'docx',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
    }

    /**
     * @return array{
     *     path: string,
     *     pages: int,
     *     extension: string,
     *     mime_type: string,
     *     files: list<string>
     * }
     */
    private function toDoc(string $pdfPath, int $pageCount, string $workspace): array
    {
        try {
            $docxResult = $this->toDocxWorkspace($pdfPath, $workspace);
            $docPath = $this->convertWithLibreOffice(
                $docxResult,
                'doc:MS Word 97',
                'doc',
                $workspace,
            );
        } catch (Throwable $exception) {
            Log::warning('DOC conversion via DOCX failed; importing PDF with LibreOffice.', [
                'error' => $exception->getMessage(),
            ]);

            $docPath = $this->convertWithLibreOffice(
                $pdfPath,
                'doc:MS Word 97',
                'doc',
                $workspace,
                'writer_pdf_import',
            );
        }

        $outputPath = $this->moveToConverted($docPath, 'doc');

        return $this->result($outputPath, $pageCount, 'doc', 'application/msword');
    }

    /**
     * @return array{
     *     path: string,
     *     pages: int,
     *     extension: string,
     *     mime_type: string,
     *     files: list<string>
     * }
     */
    private function toHtml(string $pdfPath, int $pageCount, string $workspace): array
    {
        try {
            $docxPath = $this->toDocxWorkspace($pdfPath, $workspace);
            $htmlPath = $this->convertWithLibreOffice(
                $docxPath,
                'html:HTML (StarWriter)',
                'html',
                $workspace,
            );
        } catch (Throwable $exception) {
            Log::warning('HTML conversion via DOCX failed; importing PDF with LibreOffice.', [
                'error' => $exception->getMessage(),
            ]);

            $htmlPath = $this->convertWithLibreOffice(
                $pdfPath,
                'html:HTML (StarWriter)',
                'html',
                $workspace,
                'writer_pdf_import',
            );
        }

        $this->inlineHtmlImages($htmlPath);
        $outputPath = $this->moveToConverted($htmlPath, 'html');

        return $this->result($outputPath, $pageCount, 'html', 'text/html; charset=UTF-8');
    }

    /**
     * @return array{
     *     path: string,
     *     pages: int,
     *     extension: string,
     *     mime_type: string,
     *     files: list<string>
     * }
     */
    private function toText(string $pdfPath, int $pageCount, string $workspace): array
    {
        $pdfToText = $this->requiredTool(
            'pdftotext_path',
            ['pdftotext'],
            'extract PDF text',
        );
        $workspaceOutput = $workspace.'/converted.txt';

        try {
            $this->processRunner->run(
                [$pdfToText, '-layout', '-enc', 'UTF-8', $pdfPath, $workspaceOutput],
                $this->documentTimeout(),
            );
        } catch (Throwable $exception) {
            throw PdfConversionException::failed('text extraction', $exception);
        }

        $this->assertOutput($workspaceOutput, 'text extraction');
        $outputPath = $this->moveToConverted($workspaceOutput, 'txt');

        return $this->result($outputPath, $pageCount, 'txt', 'text/plain; charset=UTF-8');
    }

    private function toDocxWorkspace(string $pdfPath, string $workspace): string
    {
        $outputPath = $workspace.'/editable.docx';
        $converted = false;

        if (config('pdf.conversion.diagram_raster_enabled', true)) {
            $converted = $this->convertWithHybridPdfToDocx($pdfPath, $outputPath);
        }

        if (! $converted) {
            $converted = $this->convertWithPdf2DocxCli($pdfPath, $outputPath);
        }

        if ($converted) {
            $this->assertOutput($outputPath, 'editable document reconstruction');
            $this->assertDocx($outputPath);
            $this->respaceDocx($outputPath, $workspace);

            return $outputPath;
        }

        Log::warning('pdf2docx is unavailable; falling back to LibreOffice for DOCX.');

        $fallback = $this->convertWithLibreOffice(
            $pdfPath,
            'docx:Office Open XML Text',
            'docx',
            $workspace,
            'writer_pdf_import',
        );

        if ($fallback !== $outputPath) {
            if (! File::move($fallback, $outputPath)) {
                throw new PdfConversionException('The converted DOCX could not be staged for download.');
            }
        }

        $this->assertDocx($outputPath);
        $this->respaceDocx($outputPath, $workspace);

        return $outputPath;
    }

    private function convertWithHybridPdfToDocx(string $pdfPath, string $outputPath): bool
    {
        $python = $this->venvPython();
        $script = base_path('resources/conversion/pdf_to_docx.py');

        if ($python === null || ! is_file($script)) {
            return false;
        }

        $dpi = max(72, min(600, (int) config('pdf.conversion.diagram_raster_dpi', 220)));

        try {
            $this->processRunner->run(
                [
                    $python,
                    $script,
                    $pdfPath,
                    $outputPath,
                    '--dpi',
                    (string) $dpi,
                ],
                $this->documentTimeout(),
            );

            return is_file($outputPath) && filesize($outputPath) > 0;
        } catch (Throwable $exception) {
            Log::warning('Hybrid PDF→DOCX conversion failed; trying pdf2docx CLI.', [
                'error' => $exception->getMessage(),
            ]);
            File::delete($outputPath);

            return false;
        }
    }

    private function convertWithPdf2DocxCli(string $pdfPath, string $outputPath): bool
    {
        $pdf2docx = $this->processRunner->find(
            config('pdf.conversion.pdf2docx_path'),
            ['pdf2docx'],
            [base_path('.venv/bin')],
        );

        if ($pdf2docx === null) {
            return false;
        }

        try {
            $command = [
                $pdf2docx,
                'convert',
                $pdfPath,
                $outputPath,
                '--delete_end_line_hyphen=True',
                '--clip_image_res_ratio=6.0',
                '--min_svg_gap_dx=30.0',
                '--min_svg_gap_dy=20.0',
                '--parse_stream_table=False',
                '--extract_stream_table=False',
                '--list_not_table=True',
                '--parse_lattice_table=True',
            ];

            if (config('pdf.conversion.docx_multi_processing', false)) {
                $command[] = '--multi_processing=True';
                $command[] = '--cpu_count='.(string) config('pdf.conversion.docx_jobs', 2);
            }

            $this->processRunner->run($command, $this->documentTimeout());

            return is_file($outputPath) && filesize($outputPath) > 0;
        } catch (Throwable $exception) {
            Log::warning('pdf2docx reconstruction failed; falling back to LibreOffice.', [
                'error' => $exception->getMessage(),
            ]);
            File::delete($outputPath);

            return false;
        }
    }

    private function venvPython(): ?string
    {
        foreach ([base_path('.venv/bin/python3'), base_path('.venv/bin/python')] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        return $this->processRunner->find(
            null,
            ['python3', 'python'],
            [base_path('.venv/bin')],
        );
    }

    /**
     * Restore missing word spaces that PDF→DOCX reconstruction often drops.
     *
     * pdf2docx frequently emits one Word run per glyph/word with no space
     * characters between runs, so per-run tools cannot see the fused text.
     * We repair at paragraph level via resources/conversion/respace_docx.py.
     */
    private function respaceDocx(string $docxPath, string $workspace): void
    {
        if (! config('pdf.conversion.docx_respace_enabled', true)) {
            return;
        }

        $venvPython = $this->venvPython();
        $script = base_path('resources/conversion/respace_docx.py');

        if ($venvPython === null || ! is_file($script)) {
            Log::warning('DOCX respacing skipped because python3 or respace_docx.py is unavailable.');

            return;
        }

        $fixedPath = $workspace.'/respaced.docx';

        try {
            $this->processRunner->run(
                [
                    $venvPython,
                    $script,
                    $docxPath,
                    '-o',
                    $fixedPath,
                ],
                $this->documentTimeout(),
            );
            $this->assertOutput($fixedPath, 'DOCX word-spacing repair');
            $this->assertDocx($fixedPath);

            if (! File::move($fixedPath, $docxPath)) {
                throw new PdfConversionException('The respaced DOCX could not replace the original.');
            }
        } catch (Throwable $exception) {
            Log::warning('DOCX word-spacing repair failed; keeping unrepaired DOCX.', [
                'error' => $exception->getMessage(),
            ]);
            File::delete($fixedPath);
        }
    }

    private function convertWithLibreOffice(
        string $inputPath,
        string $filter,
        string $extension,
        string $workspace,
        ?string $inFilter = null,
    ): string {
        $libreOffice = $this->requiredTool(
            'libreoffice_path',
            ['soffice', 'libreoffice'],
            "create {$extension} output",
        );
        $officeOutput = $workspace.'/office-output-'.Str::lower(Str::random(8));
        $profile = $workspace.'/libreoffice-profile-'.Str::lower(Str::random(8));
        File::ensureDirectoryExists($officeOutput);
        File::ensureDirectoryExists($profile);

        $command = [
            $libreOffice,
            '-env:UserInstallation='.$this->fileUrl($profile),
            '--headless',
        ];

        if (is_string($inFilter) && $inFilter !== '') {
            $command[] = '--infilter='.$inFilter;
        }

        $command = array_merge($command, [
            '--convert-to',
            $filter,
            '--outdir',
            $officeOutput,
            $inputPath,
        ]);

        try {
            $this->processRunner->run($command, $this->documentTimeout());
        } catch (Throwable $exception) {
            throw PdfConversionException::failed("{$extension} generation", $exception);
        }

        $generated = $officeOutput.'/'.pathinfo($inputPath, PATHINFO_FILENAME).'.'.$extension;
        $this->assertOutput($generated, "{$extension} generation");

        return $generated;
    }

    private function inlineHtmlImages(string $htmlPath): void
    {
        $html = File::get($htmlPath);
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            throw new PdfConversionException('HTML generation produced an unreadable document.');
        }

        foreach ($document->getElementsByTagName('img') as $image) {
            if (! $image instanceof \DOMElement) {
                continue;
            }

            $source = $image->getAttribute('src');

            if ($source === '' || str_starts_with($source, 'data:')) {
                continue;
            }

            $assetPath = dirname($htmlPath).DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, rawurldecode($source));
            $workspaceRoot = realpath(dirname($htmlPath));
            $resolvedAsset = realpath($assetPath);

            if ($workspaceRoot === false || $resolvedAsset === false || ! str_starts_with($resolvedAsset, $workspaceRoot.DIRECTORY_SEPARATOR)) {
                continue;
            }

            if (! is_file($resolvedAsset)) {
                continue;
            }

            $mime = mime_content_type($resolvedAsset) ?: 'application/octet-stream';
            $image->setAttribute('src', 'data:'.$mime.';base64,'.base64_encode(File::get($resolvedAsset)));
        }

        $serialized = $document->saveHTML();

        if ($serialized === false) {
            throw new PdfConversionException('HTML generation could not serialize the converted document.');
        }

        if (! str_starts_with(strtolower(ltrim($serialized)), '<!doctype')) {
            $serialized = '<!doctype html>'.PHP_EOL.$serialized;
        }

        File::put($htmlPath, $serialized);
    }

    /**
     * @return array{
     *     path: string,
     *     pages: int,
     *     extension: string,
     *     mime_type: string,
     *     files: list<string>
     * }
     */
    private function toImages(string $pdfPath, string $target, int $pageCount, string $workspace): array
    {
        $pdftocairo = $this->requiredTool(
            'pdftocairo_path',
            ['pdftocairo'],
            'render PDF pages as images',
        );
        $renderFormat = in_array($target, ['jpg', 'jpeg'], true) ? 'jpeg' : 'png';
        $fileExtension = $target === 'jpeg' ? 'jpeg' : ($renderFormat === 'jpeg' ? 'jpg' : 'png');
        $outputPrefix = $workspace.'/page';
        $command = [
            $pdftocairo,
            '-'.$renderFormat,
            '-r',
            (string) config('pdf.conversion.image_dpi', 300),
            '-cropbox',
        ];

        if ($renderFormat === 'png') {
            $command[] = '-transp';
        } else {
            $command[] = '-jpegopt';
            $command[] = 'quality='.(string) config('pdf.conversion.jpeg_quality', 95).',progressive=y,optimize=y';
        }

        $command[] = $pdfPath;
        $command[] = $outputPrefix;

        try {
            $this->processRunner->run($command, $this->documentTimeout());
        } catch (Throwable $exception) {
            throw PdfConversionException::failed('high-resolution page rendering', $exception);
        }

        $renderedFiles = glob($outputPrefix.'-*.'.($renderFormat === 'jpeg' ? 'jpg' : 'png')) ?: [];
        natsort($renderedFiles);
        $renderedFiles = array_values($renderedFiles);

        if (count($renderedFiles) !== $pageCount) {
            throw new PdfConversionException(
                'Page rendering was incomplete: expected '.$pageCount.' page(s), received '.count($renderedFiles).'.',
            );
        }

        if ($pageCount === 1) {
            $outputPath = $this->moveToConverted($renderedFiles[0], $fileExtension);

            return $this->result(
                $outputPath,
                $pageCount,
                $fileExtension,
                $renderFormat === 'jpeg' ? 'image/jpeg' : 'image/png',
            );
        }

        $outputPath = $this->ensureDirectory('converted').'/'.Str::uuid().'.zip';
        $zip = new ZipArchive;

        if ($zip->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new PdfConversionException('Unable to package the rendered PDF pages.');
        }

        foreach ($renderedFiles as $index => $renderedFile) {
            $zip->addFile(
                $renderedFile,
                sprintf('page-%04d.%s', $index + 1, $fileExtension),
            );
        }

        if (! $zip->close()) {
            File::delete($outputPath);

            throw new PdfConversionException('Unable to finish packaging the rendered PDF pages.');
        }

        $artifactNames = array_map(
            fn (int $index): string => sprintf('page-%04d.%s', $index + 1, $fileExtension),
            array_keys($renderedFiles),
        );

        return $this->result($outputPath, $pageCount, 'zip', 'application/zip', $artifactNames);
    }

    /**
     * @param  list<string>  $extraDirectories
     */
    private function requiredTool(
        string $configKey,
        array $names,
        string $purpose,
        array $extraDirectories = [],
    ): string {
        $path = $this->processRunner->find(
            config("pdf.conversion.{$configKey}"),
            $names,
            $extraDirectories,
        );

        if ($path === null) {
            throw PdfConversionException::missingTool($names[0], $purpose);
        }

        return $path;
    }

    private function assertDocx(string $path): void
    {
        $archive = new ZipArchive;
        $opened = $archive->open($path);
        $documentXml = $opened === true ? $archive->getFromName('word/document.xml') : false;

        if ($opened === true) {
            $archive->close();
        }

        if ($documentXml === false || $documentXml === '') {
            throw new PdfConversionException(
                'Editable document reconstruction produced an invalid DOCX file.',
            );
        }
    }

    private function assertOutput(string $path, string $stage): void
    {
        if (! is_file($path) || filesize($path) === 0) {
            throw new PdfConversionException("PDF conversion failed during {$stage}: no output file was produced.");
        }
    }

    private function moveToConverted(string $sourcePath, string $extension): string
    {
        $outputPath = $this->ensureDirectory('converted').'/'.Str::uuid().'.'.$extension;

        if (! File::move($sourcePath, $outputPath)) {
            throw new PdfConversionException('The converted file could not be saved.');
        }

        return $outputPath;
    }

    /**
     * @param  list<string>|null  $files
     * @return array{
     *     path: string,
     *     pages: int,
     *     extension: string,
     *     mime_type: string,
     *     files: list<string>
     * }
     */
    private function result(
        string $path,
        int $pages,
        string $extension,
        string $mimeType,
        ?array $files = null,
    ): array {
        return [
            'path' => $path,
            'pages' => $pages,
            'extension' => $extension,
            'mime_type' => $mimeType,
            'files' => $files ?? [$path],
        ];
    }

    private function makeWorkspace(): string
    {
        $workspace = $this->ensureDirectory('temp').'/conversion-'.Str::uuid();
        File::ensureDirectoryExists($workspace, 0700);

        return $workspace;
    }

    private function fileUrl(string $path): string
    {
        return 'file://'.str_replace('%2F', '/', rawurlencode($path));
    }

    private function timeout(): int
    {
        return (int) config('pdf.conversion.process_timeout', 120);
    }

    private function documentTimeout(): int
    {
        return (int) config('pdf.conversion.document_timeout', 900);
    }

    private function ocrTimeout(): int
    {
        return (int) config('pdf.conversion.ocr_timeout', 1800);
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
