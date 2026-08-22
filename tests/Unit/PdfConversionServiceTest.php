<?php

use App\Services\ConversionProcessRunner;
use App\Services\PdfConversionException;
use App\Services\PdfConversionService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class FakeConversionProcessRunner extends ConversionProcessRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    public bool $hasExtractableText = true;

    public bool $failOcr = false;

    public bool $failPdf2Docx = false;

    public bool $failHybridDocx = false;

    public function __construct(public int $pages = 1) {}

    public function find(?string $configuredPath, array $names, array $extraDirectories = []): ?string
    {
        return '/fake/'.$names[0];
    }

    public function run(array $command, int $timeout, ?string $workingDirectory = null): string
    {
        $this->commands[] = $command;
        $tool = basename($command[0]);

        if ($tool === 'pdfinfo') {
            return "Pages:          {$this->pages}\nEncrypted:      no";
        }

        if ($tool === 'ocrmypdf') {
            if ($this->failOcr) {
                throw new RuntimeException('tesseract is not installed');
            }

            File::copy($command[count($command) - 2], $command[count($command) - 1]);

            return '';
        }

        if ($tool === 'pdf2docx') {
            if ($this->failPdf2Docx) {
                throw new RuntimeException('pdf2docx crashed');
            }

            $archive = new ZipArchive;
            $archive->open($command[3], ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $archive->addFromString('word/document.xml', '<document><p>Editable text</p></document>');
            $archive->close();

            return '';
        }

        if (in_array($tool, ['soffice', 'libreoffice'], true)) {
            $outputDirectory = $command[array_search('--outdir', $command, true) + 1];
            $inputPath = $command[count($command) - 1];
            $filter = $command[array_search('--convert-to', $command, true) + 1];
            $extension = match (true) {
                str_starts_with($filter, 'doc:') => 'doc',
                str_starts_with($filter, 'docx:') => 'docx',
                default => 'html',
            };
            $outputPath = $outputDirectory.'/'.pathinfo($inputPath, PATHINFO_FILENAME).'.'.$extension;

            if ($extension === 'html') {
                File::put($outputDirectory.'/logo.png', 'image');
                File::put($outputPath, '<html><body><h1>Report</h1><p>Editable text</p><img src="logo.png"></body></html>');
            } elseif ($extension === 'docx') {
                $archive = new ZipArchive;
                $archive->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $archive->addFromString('word/document.xml', '<document><p>LibreOffice fallback</p></document>');
                $archive->close();
            } else {
                File::put($outputPath, 'editable doc');
            }

            return '';
        }

        if ($tool === 'pdftocairo') {
            $prefix = $command[count($command) - 1];
            $extension = in_array('-jpeg', $command, true) ? 'jpg' : 'png';

            foreach (range(1, $this->pages) as $page) {
                File::put($prefix.'-'.$page.'.'.$extension, "page {$page}");
            }

            return '';
        }

        if ($tool === 'pdftotext') {
            $destination = $command[count($command) - 1];

            if ($destination === '-') {
                return $this->hasExtractableText
                    ? 'Selectable text for detection purposes here.'
                    : '';
            }

            File::put($destination, 'Selectable text');

            return '';
        }

        if (in_array($tool, ['python3', 'python'], true)) {
            $script = (string) ($command[1] ?? '');

            if (str_contains($script, 'pdf_to_docx.py')) {
                if ($this->failHybridDocx) {
                    throw new RuntimeException('hybrid pdf_to_docx failed');
                }

                $outputPath = $command[3];
                $archive = new ZipArchive;
                $archive->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $archive->addFromString('word/document.xml', '<document><p>Editable text</p></document>');
                $archive->close();

                return '';
            }

            if (str_contains($script, 'respace_docx.py')) {
                $outputIndex = array_search('-o', $command, true);
                $inputPath = $command[2];
                $outputPath = $command[$outputIndex + 1];
                File::copy($inputPath, $outputPath);

                return '';
            }
        }

        throw new RuntimeException("Unexpected test command: {$tool}");
    }
}

beforeEach(function () {
    Storage::fake('local');
    Storage::disk('local')->makeDirectory('uploads');
    File::put(Storage::disk('local')->path('uploads/source.pdf'), '%PDF-1.4 test');
});

it('provides conversion binaries to subprocesses when the web server PATH is restricted', function () {
    $output = (new ConversionProcessRunner)->run(['/usr/bin/env'], 10);

    expect($output)->toMatch('/^PATH=.*\/opt\/homebrew\/bin/m')
        ->toMatch('/^PATH=.*\/usr\/local\/bin/m')
        ->toMatch('/^PATH=.*\.venv\/bin/m');
});

it('exposes every required target format', function () {
    expect(PdfConversionService::SUPPORTED_TARGETS)
        ->toBe(['docx', 'doc', 'jpg', 'jpeg', 'png', 'html', 'txt']);
});

it('reconstructs an editable docx without OCR when the PDF already has text', function () {
    $runner = new FakeConversionProcessRunner;
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'docx',
    );

    expect($result)
        ->pages->toBe(1)
        ->extension->toBe('docx')
        ->mime_type->toBe('application/vnd.openxmlformats-officedocument.wordprocessingml.document')
        ->and(File::exists($result['path']))->toBeTrue()
        ->and(collect($runner->commands)->map(fn (array $command) => basename($command[0]))->all())
        ->toBe(['pdfinfo', 'pdftotext', 'python3', 'python3']);
});

it('applies OCR only when the PDF has no extractable text', function () {
    $runner = new FakeConversionProcessRunner;
    $runner->hasExtractableText = false;
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'docx',
    );

    expect($result['extension'])->toBe('docx')
        ->and(collect($runner->commands)->map(fn (array $command) => basename($command[0]))->all())
        ->toBe(['pdfinfo', 'pdftotext', 'ocrmypdf', 'python3', 'python3']);
});

it('continues conversion when OCR fails on a scanned PDF', function () {
    $runner = new FakeConversionProcessRunner;
    $runner->hasExtractableText = false;
    $runner->failOcr = true;
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'docx',
    );

    expect($result['extension'])->toBe('docx')
        ->and(File::exists($result['path']))->toBeTrue()
        ->and(collect($runner->commands)->map(fn (array $command) => basename($command[0]))->all())
        ->toBe(['pdfinfo', 'pdftotext', 'ocrmypdf', 'python3', 'python3']);
});

it('falls back to LibreOffice when editable reconstruction fails', function () {
    $runner = new FakeConversionProcessRunner;
    $runner->failHybridDocx = true;
    $runner->failPdf2Docx = true;
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'docx',
    );

    $archive = new ZipArchive;
    $archive->open($result['path']);
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    expect($result['extension'])->toBe('docx')
        ->and($documentXml)->toContain('LibreOffice fallback')
        ->and(collect($runner->commands)->map(fn (array $command) => basename($command[0]))->all())
        ->toBe(['pdfinfo', 'pdftotext', 'python3', 'pdf2docx', 'soffice', 'python3']);
});

it('creates semantic self-contained HTML through the editable document pipeline', function () {
    $runner = new FakeConversionProcessRunner;
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'html',
    );
    $html = File::get($result['path']);

    expect($result['extension'])->toBe('html')
        ->and($html)->toContain('<h1>Report</h1>')
        ->toContain('<p>Editable text</p>')
        ->toContain('src="data:');
});

it('creates editable legacy DOC output through LibreOffice', function () {
    $runner = new FakeConversionProcessRunner;
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'doc',
    );

    expect($result)
        ->extension->toBe('doc')
        ->mime_type->toBe('application/msword')
        ->and(File::exists($result['path']))->toBeTrue()
        ->and(collect($runner->commands)->map(fn (array $command) => basename($command[0]))->all())
        ->toBe(['pdfinfo', 'pdftotext', 'python3', 'python3', 'soffice']);
});

it('retains OCR-aware plain text conversion for backward compatibility', function () {
    $runner = new FakeConversionProcessRunner;
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'txt',
    );

    expect($result)
        ->extension->toBe('txt')
        ->mime_type->toBe('text/plain; charset=UTF-8')
        ->and(File::get($result['path']))->toBe('Selectable text')
        ->and(collect($runner->commands)->map(fn (array $command) => basename($command[0]))->all())
        ->toBe(['pdfinfo', 'pdftotext', 'pdftotext']);
});

it('repairs fused words in reconstructed DOCX via respacer', function () {
    $runner = new class extends FakeConversionProcessRunner
    {
        public function run(array $command, int $timeout, ?string $workingDirectory = null): string
        {
            if (in_array(basename($command[0]), ['python3', 'python'], true) && str_contains((string) ($command[1] ?? ''), 'respace_docx.py')) {
                $this->commands[] = $command;
                $outputIndex = array_search('-o', $command, true);
                $inputPath = $command[2];
                $outputPath = $command[$outputIndex + 1];
                $archive = new ZipArchive;
                $archive->open($outputPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $archive->addFromString('word/document.xml', '<document><p>The quick brown fox</p></document>');
                $archive->close();

                return '';
            }

            if (in_array(basename($command[0]), ['python3', 'python'], true) && str_contains((string) ($command[1] ?? ''), 'pdf_to_docx.py')) {
                $this->commands[] = $command;
                $archive = new ZipArchive;
                $archive->open($command[3], ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $archive->addFromString('word/document.xml', '<document><p>Thequickbrownfox</p></document>');
                $archive->close();

                return '';
            }

            return parent::run($command, $timeout, $workingDirectory);
        }
    };
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'docx',
    );

    $archive = new ZipArchive;
    $archive->open($result['path']);
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    expect($documentXml)->toContain('The quick brown fox')
        ->and(collect($runner->commands)->map(fn (array $command) => basename($command[0]))->all())
        ->toContain('python3');
});

it('packages every rendered page in natural order at 300 DPI', function () {
    $runner = new FakeConversionProcessRunner(pages: 3);
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'png',
    );
    $zip = new ZipArchive;

    expect($result)
        ->pages->toBe(3)
        ->extension->toBe('zip')
        ->mime_type->toBe('application/zip')
        ->and($zip->open($result['path']))->toBeTrue()
        ->and($zip->numFiles)->toBe(3)
        ->and($zip->getNameIndex(0))->toBe('page-0001.png')
        ->and($zip->getNameIndex(2))->toBe('page-0003.png');

    $zip->close();

    $renderCommand = collect($runner->commands)->first(
        fn (array $command) => basename($command[0]) === 'pdftocairo',
    );

    expect($renderCommand)->toContain('-r', '300', '-cropbox', '-transp');
});

it('produces a JPEG alias without running OCR', function () {
    $runner = new FakeConversionProcessRunner;
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'jpeg',
    );

    expect($result)
        ->extension->toBe('jpeg')
        ->mime_type->toBe('image/jpeg')
        ->and(collect($runner->commands)->map(fn (array $command) => basename($command[0]))->all())
        ->toBe(['pdfinfo', 'pdftocairo']);
});

it('decrypts password-protected PDFs without exposing the password in process arguments', function () {
    $runner = new class extends FakeConversionProcessRunner
    {
        public bool $firstInspection = true;

        public ?string $passwordContents = null;

        public function run(array $command, int $timeout, ?string $workingDirectory = null): string
        {
            if (basename($command[0]) === 'pdfinfo' && $this->firstInspection) {
                $this->firstInspection = false;
                $this->commands[] = $command;

                throw new RuntimeException('Incorrect password');
            }

            if (basename($command[0]) === 'qpdf') {
                $this->commands[] = $command;
                $passwordArgument = collect($command)->first(
                    fn (string $argument) => str_starts_with($argument, '--password-file='),
                );
                $passwordPath = substr($passwordArgument, strlen('--password-file='));
                $this->passwordContents = File::get($passwordPath);
                File::copy($command[count($command) - 2], $command[count($command) - 1]);

                return '';
            }

            return parent::run($command, $timeout, $workingDirectory);
        }
    };
    $service = new PdfConversionService($runner);

    $result = $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'png',
        'highly-secret',
    );

    expect($result['extension'])->toBe('png')
        ->and($runner->passwordContents)->toBe('highly-secret')
        ->and(collect($runner->commands)->flatten()->contains('highly-secret'))->toBeFalse();
});

it('reports a meaningful error when a required tool is unavailable', function () {
    $runner = new class extends FakeConversionProcessRunner
    {
        public function find(?string $configuredPath, array $names, array $extraDirectories = []): ?string
        {
            return $names[0] === 'pdfinfo' ? null : parent::find($configuredPath, $names, $extraDirectories);
        }
    };
    $service = new PdfConversionService($runner);

    expect(fn () => $service->convertFromPdf(
        Storage::disk('local')->path('uploads/source.pdf'),
        'png',
    ))->toThrow(PdfConversionException::class, 'pdfinfo is not installed or configured');
});

it('renders real PDF pages at print resolution when Poppler is available', function () {
    $runner = new ConversionProcessRunner;
    $pdfInfo = $runner->find(config('pdf.conversion.pdfinfo_path'), ['pdfinfo']);
    $pdfToCairo = $runner->find(config('pdf.conversion.pdftocairo_path'), ['pdftocairo']);

    if ($pdfInfo === null || $pdfToCairo === null) {
        $this->markTestSkipped('Poppler is not installed.');
    }

    $inputPath = Storage::disk('local')->path('uploads/source.pdf');
    $pdf = new FPDF;

    foreach (['First page', 'Second page'] as $text) {
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->Cell(0, 12, $text);
    }

    $pdf->Output('F', $inputPath);

    $result = (new PdfConversionService($runner))->convertFromPdf($inputPath, 'png');
    $zip = new ZipArchive;
    $zip->open($result['path']);
    $dimensions = getimagesizefromstring($zip->getFromName('page-0001.png'));
    $zip->close();

    expect($result['pages'])->toBe(2)
        ->and($dimensions)->not->toBeFalse()
        ->and($dimensions[0])->toBeGreaterThanOrEqual(2479)
        ->toBeLessThanOrEqual(2481)
        ->and($dimensions[1])->toBeGreaterThanOrEqual(3507)
        ->toBeLessThanOrEqual(3509);
})->group('conversion-integration');

it('reconstructs real PDF text and tables as editable DOCX content when pdf2docx is available', function () {
    $runner = new ConversionProcessRunner;
    $pdfInfo = $runner->find(config('pdf.conversion.pdfinfo_path'), ['pdfinfo']);
    $pdf2docx = $runner->find(
        config('pdf.conversion.pdf2docx_path'),
        ['pdf2docx'],
        [base_path('.venv/bin')],
    );

    if ($pdfInfo === null || $pdf2docx === null) {
        $this->markTestSkipped('pdf2docx or Poppler is not installed.');
    }

    config(['pdf.conversion.ocr_enabled' => false]);
    $inputPath = Storage::disk('local')->path('uploads/source.pdf');
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 18);
    $pdf->Cell(0, 12, 'Editable report heading', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, 'This paragraph must remain selectable and editable.');
    $pdf->Ln(5);
    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(80, 10, 'Product', 1);
    $pdf->Cell(80, 10, 'Revenue', 1, 1);
    $pdf->SetFont('Arial', '', 11);
    $pdf->Cell(80, 10, 'Powerhouse', 1);
    $pdf->Cell(80, 10, '1000', 1, 1);
    $pdf->Output('F', $inputPath);

    $result = (new PdfConversionService($runner))->convertFromPdf($inputPath, 'docx');
    $archive = new ZipArchive;
    $archive->open($result['path']);
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    expect($documentXml)->toContain('Editable report heading')
        ->toContain('selectable and editable')
        ->toContain('Powerhouse')
        ->toContain('<w:tbl');
})->group('conversion-integration');

it('converts a real PDF through every installed output backend', function () {
    $runner = new ConversionProcessRunner;
    $requiredTools = [
        $runner->find(config('pdf.conversion.pdfinfo_path'), ['pdfinfo']),
        $runner->find(config('pdf.conversion.pdftocairo_path'), ['pdftocairo']),
        $runner->find(config('pdf.conversion.pdftotext_path'), ['pdftotext']),
        $runner->find(config('pdf.conversion.pdf2docx_path'), ['pdf2docx'], [base_path('.venv/bin')]),
        $runner->find(config('pdf.conversion.libreoffice_path'), ['soffice', 'libreoffice']),
    ];

    if (in_array(null, $requiredTools, true)) {
        $this->markTestSkipped('The complete PDF conversion toolchain is not installed.');
    }

    config(['pdf.conversion.ocr_enabled' => false]);
    $inputPath = Storage::disk('local')->path('uploads/source.pdf');
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Arial', 'B', 20);
    $pdf->Cell(0, 12, 'Toolchain smoke test', 0, 1);
    $pdf->SetFont('Arial', '', 12);
    $pdf->MultiCell(0, 7, 'This content must survive each editable conversion backend.');
    $pdf->Output('F', $inputPath);
    $service = new PdfConversionService($runner);

    $doc = $service->convertFromPdf($inputPath, 'doc');
    $html = $service->convertFromPdf($inputPath, 'html');
    $text = $service->convertFromPdf($inputPath, 'txt');
    $jpg = $service->convertFromPdf($inputPath, 'jpg');
    $jpeg = $service->convertFromPdf($inputPath, 'jpeg');
    $png = $service->convertFromPdf($inputPath, 'png');
    $htmlText = preg_replace(
        '/\s+/',
        ' ',
        strip_tags(html_entity_decode(File::get($html['path']))),
    );

    expect($doc['extension'])->toBe('doc')
        ->and(filesize($doc['path']))->toBeGreaterThan(0)
        ->and(File::get($html['path']))->not->toMatch('/<!doctype html>.*<!DOCTYPE html>/is')
        ->and($htmlText)->toContain('Toolchain smoke test')
        ->and(File::get($text['path']))->toContain('Toolchain smoke test')
        ->and(getimagesize($jpg['path']))->not->toBeFalse()
        ->and($jpg['mime_type'])->toBe('image/jpeg')
        ->and(getimagesize($jpeg['path']))->not->toBeFalse()
        ->and($jpeg['extension'])->toBe('jpeg')
        ->and(getimagesize($png['path']))->not->toBeFalse()
        ->and($png['mime_type'])->toBe('image/png');
})->group('conversion-integration');

it('runs the real OCR normalization stage before editable conversion', function () {
    $runner = new ConversionProcessRunner;
    $requiredTools = [
        $runner->find(config('pdf.conversion.pdfinfo_path'), ['pdfinfo']),
        $runner->find(config('pdf.conversion.ocrmypdf_path'), ['ocrmypdf']),
        $runner->find(config('pdf.conversion.pdf2docx_path'), ['pdf2docx'], [base_path('.venv/bin')]),
    ];

    if (in_array(null, $requiredTools, true)) {
        $this->markTestSkipped('OCRmyPDF or pdf2docx is not installed.');
    }

    config(['pdf.conversion.ocr_enabled' => true]);
    $inputPath = Storage::disk('local')->path('uploads/source.pdf');
    $pdf = new FPDF;
    $pdf->AddPage();
    $pdf->SetFont('Arial', '', 14);
    $pdf->Cell(0, 12, 'OCR normalization smoke test');
    $pdf->Output('F', $inputPath);

    $result = (new PdfConversionService($runner))->convertFromPdf($inputPath, 'docx');
    $archive = new ZipArchive;
    $archive->open($result['path']);
    $documentXml = $archive->getFromName('word/document.xml');
    $archive->close();

    expect($documentXml)->toContain('OCR normalization smoke test');
})->group('conversion-integration');
