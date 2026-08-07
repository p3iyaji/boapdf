@extends('layouts.app')

@section('title', 'Admin documents - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6">
    <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Documents</h1>
    <p class="mt-1 text-sm text-brand-900/70">Browse and remove documents across all users.</p>
</div>

<form method="GET" action="{{ route('admin.documents.index') }}" class="mb-4 grid grid-cols-1 gap-3 rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm sm:grid-cols-4">
    <div class="sm:col-span-2">
        <label for="q" class="mb-1 block text-xs font-medium text-brand-800/80">Search</label>
        <input id="q" type="search" name="q" value="{{ $search }}" placeholder="Filename, path, or owner"
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="status" class="mb-1 block text-xs font-medium text-brand-800/80">Status</label>
        <select id="status" name="status" class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm">
            <option value="">Any</option>
            @foreach (['pending', 'processing', 'completed', 'failed'] as $option)
                <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label for="operation" class="mb-1 block text-xs font-medium text-brand-800/80">Operation</label>
        <select id="operation" name="operation" class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm">
            <option value="">Any</option>
            @foreach (['upload', 'merged', 'compressed', 'converted', 'signed', 'capture'] as $option)
                <option value="{{ $option }}" @selected($operation === $option)>{{ ucfirst($option) }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-4">
        <button type="submit" class="rounded-lg border border-brand-900/15 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-950 hover:bg-brand-100">Filter</button>
    </div>
</form>

<div class="overflow-x-auto rounded-xl border border-brand-900/10 bg-white/95 shadow-sm">
    <table class="min-w-full divide-y divide-brand-900/10 text-left text-sm">
        <thead class="bg-brand-50/80 text-xs uppercase tracking-wide text-brand-800/70">
            <tr>
                <th class="px-4 py-3 font-semibold">Document</th>
                <th class="px-4 py-3 font-semibold">Owner</th>
                <th class="px-4 py-3 font-semibold">Type</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold">Size</th>
                <th class="px-4 py-3 font-semibold">Created</th>
                <th class="px-4 py-3 font-semibold"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-brand-900/5">
            @forelse ($documents as $document)
                <tr>
                    <td class="px-4 py-3">
                        <div class="max-w-xs truncate font-medium text-brand-950">{{ $document->original_name }}</div>
                        <div class="max-w-xs truncate text-xs text-brand-800/60">{{ $document->file_path }}</div>
                    </td>
                    <td class="px-4 py-3 text-brand-800/80">
                        @if ($document->user)
                            <a href="{{ route('admin.users.show', $document->user) }}" class="hover:text-accent-700">{{ $document->user->email }}</a>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $document->operation_type }}</td>
                    <td class="px-4 py-3">{{ $document->status }}</td>
                    <td class="px-4 py-3 tabular-nums">{{ $document->human_file_size }}</td>
                    <td class="px-4 py-3 text-brand-800/70">{{ $document->created_at?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.documents.show', $document) }}" class="text-sm font-semibold text-brand-900 hover:text-accent-700">View</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" class="px-4 py-8 text-center text-brand-800/70">No documents found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $documents->links() }}</div>
@endsection
