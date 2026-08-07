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
        $documents = Document::query()
            ->with('user')
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('original_name', 'like', $term)
                        ->orWhere('file_path', 'like', $term)
                        ->orWhereHas('user', function ($userQuery) use ($term): void {
                            $userQuery->where('name', 'like', $term)
                                ->orWhere('email', 'like', $term);
                        });
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('operation'), fn ($query) => $query->where('operation_type', $request->string('operation')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.documents.index', [
            'documents' => $documents,
            'search' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
            'operation' => $request->string('operation')->toString(),
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
