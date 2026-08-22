<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConvertPdfRequest;
use App\Jobs\ProcessPdfConvertJob;
use App\Models\Document;
use App\Services\PdfConversionService;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class ConvertController extends Controller
{
    /**
     * @return list<array{
     *     id: string,
     *     label: string,
     *     formats: list<array{
     *         value: string,
     *         label: string,
     *         hint: string,
     *         recommended?: bool
     *     }>
     * }>
     */
    public static function formatGroups(): array
    {
        return [
            [
                'id' => 'editable',
                'label' => 'Editable documents',
                'formats' => [
                    [
                        'value' => 'docx',
                        'label' => 'Word (DOCX)',
                        'hint' => 'Best for editing text, tables, and lists in Microsoft Word or Google Docs.',
                        'recommended' => true,
                    ],
                    [
                        'value' => 'doc',
                        'label' => 'Word 97–2003 (DOC)',
                        'hint' => 'Legacy Word format. Prefer DOCX unless you need older software.',
                    ],
                    [
                        'value' => 'html',
                        'label' => 'HTML',
                        'hint' => 'Single web page with inlined images for browsers or email drafts.',
                    ],
                ],
            ],
            [
                'id' => 'images',
                'label' => 'Page images',
                'formats' => [
                    [
                        'value' => 'png',
                        'label' => 'PNG',
                        'hint' => 'Sharp pages at 300 DPI. Multi-page PDFs download as a ZIP.',
                    ],
                    [
                        'value' => 'jpg',
                        'label' => 'JPEG',
                        'hint' => 'Smaller files for sharing. Multi-page PDFs download as a ZIP.',
                    ],
                ],
            ],
            [
                'id' => 'text',
                'label' => 'Plain text',
                'formats' => [
                    [
                        'value' => 'txt',
                        'label' => 'Text (TXT)',
                        'hint' => 'Extract readable text only—no layout, images, or tables.',
                    ],
                ],
            ],
        ];
    }

    public function create(Request $request): View
    {
        $documents = Document::query()
            ->forUser($request->user()->id)
            ->completedPdfs()
            ->latest()
            ->get();

        $convertDocuments = $documents->map(fn (Document $d): array => [
            'id' => $d->id,
            'name' => $d->original_name,
            'pages' => $d->pages,
            'size' => $d->human_file_size,
        ])->values()->all();

        $selectedId = (int) old('document_id', $request->query('document', 0));
        $defaultTarget = (string) old('target', 'docx');

        return view('pdf.convert', [
            'documents' => $documents,
            'convertDocuments' => $convertDocuments,
            'formatGroups' => self::formatGroups(),
            'selectedId' => $selectedId,
            'defaultTarget' => $defaultTarget,
            'targets' => PdfConversionService::SUPPORTED_TARGETS,
        ]);
    }

    public function store(ConvertPdfRequest $request): RedirectResponse
    {
        /** @var Document $source */
        $source = Document::query()
            ->where('id', $request->integer('document_id'))
            ->where('user_id', $request->user()->id)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf')
            ->firstOrFail();

        $target = (string) $request->input('target');
        $password = $request->string('password')->toString() ?: null;
        $baseName = pathinfo($source->original_name, PATHINFO_FILENAME);

        try {
            $document = Document::create([
                'user_id' => $request->user()->id,
                'original_name' => $baseName.'.'.$target,
                'file_path' => 'converted/pending-'.uniqid('', true).'.'.$target,
                'file_size' => 0,
                'mime_type' => 'application/octet-stream',
                'pages' => 0,
                'status' => Document::STATUS_PROCESSING,
                'operation_type' => Document::OP_CONVERTED,
                'parent_document_id' => $source->id,
                'metadata' => ['target' => $target],
            ]);

            ProcessPdfConvertJob::dispatch($document->id, $source->id, $target, $password);
            $document->refresh();

            if ($document->status === Document::STATUS_FAILED) {
                return back()->withErrors(['convert' => 'Could not convert this PDF. Please try again.']);
            }

            return redirect()->route('pdf.convert.progress', $document);
        } catch (Throwable $e) {
            Log::error('PDF conversion failed', [
                'document_id' => $source->id,
                'target' => $target,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return back()->withErrors(['convert' => SafeUserMessage::from($e, 'Could not convert this PDF')]);
        }
    }

    public function progress(Request $request, Document $document): View
    {
        $this->authorizeConversion($request, $document);

        $target = (string) ($document->metadata['target'] ?? pathinfo($document->original_name, PATHINFO_EXTENSION));

        return view('pdf.convert-progress', [
            'document' => $document,
            'targetLabel' => $this->targetLabel($target),
            'statusUrl' => route('pdf.convert.status', $document),
            'downloadUrl' => route('pdf.download', $document),
            'initialStatus' => $this->statusPayload($document),
        ]);
    }

    public function status(Request $request, Document $document): JsonResponse
    {
        $this->authorizeConversion($request, $document);

        return response()->json($this->statusPayload($document));
    }

    private function authorizeConversion(Request $request, Document $document): void
    {
        $this->authorize('view', $document);

        abort_unless(
            $document->operation_type === Document::OP_CONVERTED,
            404,
        );
    }

    /**
     * @return array{
     *     status: string,
     *     ready: bool,
     *     failed: bool,
     *     name: string,
     *     size: string|null,
     *     pages: int,
     *     target: string,
     *     error: string|null,
     *     download_url: string|null
     * }
     */
    private function statusPayload(Document $document): array
    {
        $ready = $document->isFileReady();
        $target = (string) ($document->metadata['target'] ?? pathinfo($document->original_name, PATHINFO_EXTENSION));

        return [
            'status' => $document->status,
            'ready' => $ready,
            'failed' => $document->status === Document::STATUS_FAILED,
            'name' => $document->original_name,
            'size' => $ready ? $document->human_file_size : null,
            'pages' => (int) $document->pages,
            'target' => $target,
            'error' => $document->status === Document::STATUS_FAILED
                ? (string) ($document->metadata['error'] ?? 'Conversion failed. Please try again.')
                : null,
            'download_url' => $ready ? route('pdf.download', $document) : null,
        ];
    }

    private function targetLabel(string $target): string
    {
        foreach (self::formatGroups() as $group) {
            foreach ($group['formats'] as $format) {
                if ($format['value'] === $target) {
                    return $format['label'];
                }
            }
        }

        return strtoupper($target);
    }
}
