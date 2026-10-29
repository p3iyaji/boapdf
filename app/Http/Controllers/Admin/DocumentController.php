<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Support\DocumentsDisk;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString();
        $status = $request->string('status')->toString();
        $operation = $request->string('operation')->toString();

        $documents = Document::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search): void {
                $term = '%'.$search.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('original_name', 'like', $term)
                        ->orWhere('file_path', 'like', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term): void {
                            $userQuery->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->status($status)
            ->operation($operation)
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.documents.index', [
            'documents' => $documents,
            'search' => $search,
            'status' => in_array($status, Document::statuses(), true) ? $status : '',
            'operation' => in_array($operation, Document::operationTypes(), true) ? $operation : '',
            'statuses' => Document::statuses(),
            'operations' => Document::operationTypes(),
        ]);
    }

    public function show(Document $document): View
    {
        $document->load(['user', 'parent', 'signatureRequests']);

        return view('admin.documents.show', ['document' => $document]);
    }

    public function destroy(Document $document): RedirectResponse
    {
        if (filled($document->file_path) && DocumentsDisk::disk()->exists($document->file_path)) {
            DocumentsDisk::disk()->delete($document->file_path);
        }

        $document->delete();

        return redirect()
            ->route('admin.documents.index')
            ->with('success', 'Document deleted.');
    }
}
