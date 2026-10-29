<?php

use App\Services\PdfEditException;
use App\Services\PdfEditService;
use Illuminate\Support\Facades\Process;

function editPython(): ?string
{
    $python = base_path('.venv/bin/python');

    return is_executable($python) ? $python : null;
}

function makeEditablePdf(string $path): array
{
    $script = <<<'PY'
import fitz, json, sys
doc = fitz.open()
page = doc.new_page()
page.insert_text((72, 100), "Hello world", fontsize=16, fontname="helv")
widget = fitz.Widget()
widget.field_type = fitz.PDF_WIDGET_TYPE_TEXT
widget.field_name = "Name"
widget.field_value = "Old"
widget.rect = fitz.Rect(72, 140, 260, 164)
page.add_widget(widget)
second = doc.new_page()
second.insert_text((72, 100), "Second page stays", fontsize=14, fontname="helv")
doc.save(sys.argv[1])
doc.close()
saved = fitz.open(sys.argv[1])
print(json.dumps(list(saved[0].search_for("Hello world")[0])))
saved.close()
PY;

    $result = Process::path(base_path())->run([editPython(), '-c', $script, $path]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    return json_decode(trim($result->output()), true);
}

function pdfPlainText(string $path, int $page = 0): string
{
    $script = <<<'PY'
import fitz, sys
doc = fitz.open(sys.argv[1])
print(doc[int(sys.argv[2])].get_text().replace("\u00a0", " "))
doc.close()
PY;

    $result = Process::path(base_path())->run([editPython(), '-c', $script, $path, (string) $page]);

    expect($result->successful())->toBeTrue($result->errorOutput());

    return $result->output();
}

it('replaces a sentence, fills a form field, and adds a highlight', function () {
    if (editPython() === null) {
        $this->markTestSkipped('PyMuPDF is not installed.');
    }

    $source = storage_path('framework/edit-source.pdf');
    $rect = makeEditablePdf($source);

    $result = app(PdfEditService::class)->applyContent($source, [
        'replacements' => [[
            'page' => 1,
            'rects' => [$rect],
            'text' => 'Hello there',
            'size' => 16,
        ]],
        'highlights' => [[
            'page' => 2,
            'rects' => [[72, 86, 210, 108]],
        ]],
        'texts' => [[
            'page' => 1,
            'rect' => [72, 230, 280, 260],
            'text' => 'Added note',
            'size' => 12,
            'color' => '#111827',
        ]],
        'fields' => [[
            'name' => 'Name',
            'value' => 'Ada',
        ]],
    ]);

    $text = pdfPlainText($result['path']);

    expect($text)->toContain('Hello there')
        ->and($text)->toContain('Added note')
        ->and($text)->not->toContain('Hello world')
        ->and($result['pages'])->toBe(2);

    $field = Process::path(base_path())->run([
        editPython(),
        '-c',
        'import fitz,sys; doc=fitz.open(sys.argv[1]); print(doc[0].first_widget.field_value)',
        $result['path'],
    ]);

    expect(trim($field->output()))->toBe('Ada');

    @unlink($source);
    @unlink($result['path']);
});

it('refuses a replacement that does not fit the selected line', function () {
    if (editPython() === null) {
        $this->markTestSkipped('PyMuPDF is not installed.');
    }

    $source = storage_path('framework/edit-overflow.pdf');
    $rect = makeEditablePdf($source);

    expect(fn () => app(PdfEditService::class)->applyContent($source, [
        'replacements' => [[
            'page' => 1,
            'rects' => [$rect],
            'text' => 'This replacement is far too long for the selected line of text',
            'size' => 16,
        ]],
    ]))->toThrow(PdfEditException::class, 'Shorten it');

    @unlink($source);
});

it('removes redacted text and keeps covered text extractable', function () {
    if (editPython() === null) {
        $this->markTestSkipped('PyMuPDF is not installed.');
    }

    $source = storage_path('framework/edit-cover.pdf');
    $rect = makeEditablePdf($source);
    $service = app(PdfEditService::class);

    $redacted = $service->applyContent($source, [
        'redactions' => [['page' => 1, 'rect' => $rect]],
    ]);
    $covered = $service->applyContent($source, [
        'covers' => [['page' => 1, 'rect' => $rect]],
    ]);

    expect(pdfPlainText($redacted['path']))->not->toContain('Hello world');
    expect(pdfPlainText($covered['path']))->toContain('Hello world');

    @unlink($source);
    @unlink($redacted['path']);
    @unlink($covered['path']);
});

it('reorders pages, inserts a blank page, and rotates the first page', function () {
    if (editPython() === null) {
        $this->markTestSkipped('PyMuPDF is not installed.');
    }

    $source = storage_path('framework/edit-pages.pdf');
    makeEditablePdf($source);

    $result = app(PdfEditService::class)->applyPages($source, [
        ['source' => 2, 'rotate' => 90],
        ['blank' => true, 'rotate' => 0],
        ['source' => 1, 'rotate' => 0],
    ]);

    expect($result['pages'])->toBe(3)
        ->and(pdfPlainText($result['path'], 0))->toContain('Second page stays')
        ->and(trim(pdfPlainText($result['path'], 1)))->toBe('')
        ->and(pdfPlainText($result['path'], 2))->toContain('Hello world');

    @unlink($source);
    @unlink($result['path']);
});
