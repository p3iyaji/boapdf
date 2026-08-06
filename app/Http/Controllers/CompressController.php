<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompressPdfRequest;
use App\Jobs\ProcessPdfCompressJob;
use App\Models\Document;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;
use Throwable;

class CompressController extends Controller
{
    /**
     * @var array<string, array{label: string, hint: string, qualityPct: int, sizePct: int}>
     */
    private const LEVEL_META = [
        'low' => [
            'label' => 'High quality',
            'hint' => 'Best for print. Little size change.',
            'qualityPct' => 85,
            'sizePct' => 15,
        ],
        'medium' => [
            'label' => 'Balanced',
            'hint' => 'Still print-usable, noticeably smaller.',
            'qualityPct' => 60,
            'sizePct' => 40,
        ],
        'recommended' => [
            'label' => 'Recommended',
            'hint' => 'Great for email and sharing.',
            'qualityPct' => 40,
            'sizePct' => 60,
        ],
        'maximum' => [
            'label' => 'Smallest',
            'hint' => 'Best for large scans and photos.',
            'qualityPct' => 20,
            'sizePct' => 80,
        ],
    ];

    public function create(Request $request): View
    {
        $documents = Document::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf')
            ->latest()
            ->get();

        $compressDocuments = $documents->map(fn (Document $d): array => [
            'id' => $d->id,
            'name' => $d->original_name,
            'pages' => $d->pages,
            'size' => $d->human_file_size,
        ])->values()->all();

        $levels = config('pdf.compression_levels');
        $levelOptions = collect($levels)->map(function (string $level): array {
            $meta = self::LEVEL_META[$level] ?? [
                'label' => ucfirst($level),
                'hint' => '',
                'qualityPct' => 50,
                'sizePct' => 50,
            ];

            return [
                'value' => $level,
                ...$meta,
            ];
        })->values()->all();

        $selectedId = (int) old('document_id', $request->query('document', 0));

        return view('pdf.compress', [
            'compressDocuments' => $compressDocuments,
            'levelOptions' => $levelOptions,
            'default' => config('pdf.default_compression'),
            'selectedId' => $selectedId,
        ]);
    }

    public function store(CompressPdfRequest $request): RedirectResponse
    {
        /** @var Document $source */
        $source = Document::query()
            ->where('id', $request->integer('document_id'))
            ->where('user_id', $request->user()->id)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf')
            ->firstOrFail();

        $level = $request->input('level', config('pdf.default_compression'));

        try {
            $document = Document::create([
                'user_id' => $request->user()->id,
                'original_name' => pathinfo($source->original_name, PATHINFO_FILENAME).'-compressed.pdf',
                'file_path' => 'compressed/pending-'.uniqid('', true).'.pdf',
                'file_size' => 0,
                'mime_type' => 'application/pdf',
                'pages' => 0,
                'status' => Document::STATUS_PROCESSING,
                'operation_type' => Document::OP_COMPRESSED,
                'parent_document_id' => $source->id,
                'metadata' => ['level' => $level],
            ]);

            ProcessPdfCompressJob::dispatch($document->id, $source->id, $level);
            $document->refresh();

            if ($document->status === Document::STATUS_FAILED) {
                return back()->withErrors(['compress' => 'Could not compress this PDF. Please try again.']);
            }

            return redirect()->route('pdf.show', $document)
                ->with('success', $this->successMessage($document));
        } catch (Throwable $e) {
            Log::error('PDF compression failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['compress' => SafeUserMessage::from($e, 'Could not compress this PDF')]);
        }
    }

    private function successMessage(Document $document): string
    {
        if ($document->status === Document::STATUS_PROCESSING) {
            return 'Compression queued. Refresh this page shortly for the result.';
        }

        $original = (int) ($document->metadata['original_size'] ?? 0);
        $new = (int) ($document->metadata['new_size'] ?? $document->file_size);

        if ($original > 0 && $new < $original) {
            $percent = (int) round((1 - ($new / $original)) * 100);
            $saved = Number::fileSize($original - $new);

            return "Compressed — {$percent}% smaller (saved {$saved}).";
        }

        if ($original > 0 && $new >= $original) {
            return 'Compression finished. This file was already well optimized, so size barely changed.';
        }

        return 'PDF compressed successfully.';
    }
}
