@extends('layouts.app')

@section('title', $document->original_name.' - Admin - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
        <h1 class="truncate font-display text-2xl font-bold text-brand-950 sm:text-3xl">{{ $document->original_name }}</h1>
        <p class="mt-1 break-all text-sm text-brand-900/70">{{ $document->file_path }}</p>
    </div>
    <form method="POST" action="{{ route('admin.documents.destroy', $document) }}" onsubmit="return confirm('Permanently delete this document and its file?')">
        @csrf
        @method('DELETE')
        <button type="submit" class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-2 text-sm font-semibold text-danger-900 hover:bg-danger-100">Delete</button>
    </form>
</div>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Owner</p>
        <p class="mt-1 font-semibold text-brand-950">
            @if ($document->user)
                <a href="{{ route('admin.users.show', $document->user) }}" class="hover:text-accent-700">{{ $document->user->email }}</a>
            @else
                —
            @endif
        </p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Status</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $document->status }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Operation</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $document->operation_type }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Size / pages</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $document->human_file_size }} · {{ $document->pages }} pages</p>
    </div>
</div>

@if ($document->signatureRequests->isNotEmpty())
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm sm:p-5">
        <h2 class="mb-3 text-lg font-semibold text-brand-950">Signature requests</h2>
        <ul class="divide-y divide-brand-900/5 text-sm">
            @foreach ($document->signatureRequests as $request)
                <li class="flex items-center justify-between gap-3 py-2">
                    <div>
                        <a href="{{ route('admin.signature-requests.show', $request) }}" class="font-medium text-brand-950 hover:text-accent-700">{{ $request->signer_email }}</a>
                        <p class="text-xs text-brand-800/60">{{ $request->status }}</p>
                    </div>
                    <span class="text-xs text-brand-800/60">{{ $request->created_at?->format('Y-m-d') }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
@endsection
