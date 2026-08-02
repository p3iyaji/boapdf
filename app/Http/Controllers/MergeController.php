<?php

namespace App\Http\Controllers;

use App\Http\Requests\MergePdfRequest;
use App\Jobs\ProcessPdfMergeJob;
use App\Models\Document;
use App\Support\SafeUserMessage;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

class MergeController extends Controller
{
    public function create(Request $request): View
    {
        $documents = Document::query()
            ->where('user_id', $request->user()->id)
            ->where('status', Document::STATUS_COMPLETED)
            ->where('mime_type', 'application/pdf')
            ->latest()
            ->get();

        $mergeDocuments = $documents->map(fn (Document $d): array => [
            'id' => $d->id,
            'name' => $d->original_name,
            'pages' => $d->pages,
            'size' => $d->human_file_size,
        ])->values()->all();

        return view('pdf.merge', [
            'mergeDocuments' => $mergeDocuments,
        ]);
    }

    public function store(MergePdfRequest $request): RedirectResponse
    {
        $documents = $request->documents();
        $name = $request->input('output_name') ?: 'merged-'.now()->format('Ymd-His').'.pdf';
        $sourceIds = array_map(fn (Document $d): int => $d->id, $documents);

        try {
            $document = Document::create([
                'user_id' => $request->user()->id,
                'original_name' => str_ends_with($name, '.pdf') ? $name : $name.'.pdf',
                'file_path' => 'merged/pending-'.uniqid('', true).'.pdf',
                'file_size' => 0,
                'mime_type' => 'application/pdf',
                'pages' => 0,
                'status' => Document::STATUS_PROCESSING,
                'operation_type' => Document::OP_MERGED,
                'parent_document_id' => $documents[0]->id ?? null,
                'metadata' => [
                    'source_ids' => $sourceIds,
                ],
            ]);

            ProcessPdfMergeJob::dispatch($document->id, $sourceIds);

            $document->refresh();

            if ($document->status === Document::STATUS_FAILED) {
                return back()->withErrors(['merge' => 'Could not merge PDFs. Please try again.']);
            }

            $message = $document->status === Document::STATUS_COMPLETED
                ? 'Merged '.count($documents).' documents.'
                : 'Merge queued. Refresh this page shortly for the result.';

            return redirect()->route('pdf.show', $document)->with('success', $message);
        } catch (Throwable $e) {
            Log::error('PDF merge failed', ['error' => $e->getMessage()]);

            return back()->withErrors(['merge' => SafeUserMessage::from($e, 'Could not merge PDFs')]);
        }
    }
}
