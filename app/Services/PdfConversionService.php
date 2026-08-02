<?php

namespace App\Services;

use App\Support\DocumentsDisk;
use DOMDocument;
use Illuminate\Support\Facades\File;
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

    private function applyOcr(string $pdfPath, string $workspace): string
    {
        if (! config('pdf.conversion.ocr_enabled', true)) {
            return $pdfPath;
        }

        $ocrMyPdf = $this->requiredTool('ocrmypdf_path', ['ocrmypdf'], 'recognize scanned PDF pages');
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
        } catch (Throwable $exception) {
            throw PdfConversionException::failed('optical character recognition', $exception);
        }

        $this->assertOutput($outputPath, 'optical character recognition');

        return $outputPath;
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
        $docxResult = $this->toDocxWorkspace($pdfPath, $workspace);
        $docPath = $this->convertWithLibreOffice(
            $docxResult,
            'doc:MS Word 97',
            'doc',
            $workspace,
        );
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
        $docxPath = $this->toDocxWorkspace($pdfPath, $workspace);
        $htmlPath = $this->convertWithLibreOffice(
            $docxPath,
            'html:HTML (StarWriter)',
            'html',
            $workspace,
        );
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
        $pdf2docx = $this->requiredTool(
            'pdf2docx_path',
            ['pdf2docx'],
            'create editable Word documents',
            [base_path('.venv/bin')],
        );
        $outputPath = $workspace.'/editable.docx';

        try {
            $this->processRunner->run(
                [
                    $pdf2docx,
                    'convert',
                    $pdfPath,
                    $outputPath,
                    '--multi_processing=True',
                    '--cpu_count='.(string) config('pdf.conversion.docx_jobs', 2),
                ],
                $this->documentTimeout(),
            );
        } catch (Throwable $exception) {
            throw PdfConversionException::failed('editable document reconstruction', $exception);
        }

        $this->assertOutput($outputPath, 'editable document reconstruction');
        $this->assertDocx($outputPath);

        return $outputPath;
    }

    private function convertWithLibreOffice(
        string $inputPath,
        string $filter,
        string $extension,
        string $workspace,
    ): string {
        $libreOffice = $this->requiredTool(
            'libreoffice_path',
            ['soffice', 'libreoffice'],
            "create {$extension} output",
        );
        $officeOutput = $workspace.'/office-output';
        $profile = $workspace.'/libreoffice-profile';
        File::ensureDirectoryExists($officeOutput);
        File::ensureDirectoryExists($profile);

        try {
            $this->processRunner->run(
                [
                    $libreOffice,
                    '-env:UserInstallation='.$this->fileUrl($profile),
                    '--headless',
                    '--convert-to',
                    $filter,
                    '--outdir',
                    $officeOutput,
                    $inputPath,
                ],
                $this->documentTimeout(),
            );
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
        $hasDocument = $opened === true && $archive->locateName('word/document.xml') !== false;

        if ($opened === true) {
            $archive->close();
        }

        if (! $hasDocument) {
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
