@extends('layouts.app')

@section('title', 'Conversion logs - Admin - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6">
    <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Conversion logs</h1>
    <p class="mt-1 text-sm text-brand-900/70">Read-only audit of convert and compress attempts.</p>
</div>

<form method="GET" action="{{ route('admin.conversion-logs.index') }}" class="mb-4 flex flex-col gap-3 rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm sm:flex-row sm:items-end">
    <div class="min-w-0 flex-1">
        <label for="q" class="mb-1 block text-xs font-medium text-brand-800/80">Search</label>
        <input id="q" type="search" name="q" value="{{ $search }}" placeholder="Format or error text"
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="status" class="mb-1 block text-xs font-medium text-brand-800/80">Status</label>
        <select id="status" name="status" class="rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm">
            <option value="">Any</option>
            <option value="success" @selected($status === 'success')>Success</option>
            <option value="failed" @selected($status === 'failed')>Failed</option>
        </select>
    </div>
    <button type="submit" class="rounded-lg border border-brand-900/15 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-950 hover:bg-brand-100">Filter</button>
</form>

<div class="overflow-x-auto rounded-xl border border-brand-900/10 bg-white/95 shadow-sm">
    <table class="min-w-full divide-y divide-brand-900/10 text-left text-sm">
        <thead class="bg-brand-50/80 text-xs uppercase tracking-wide text-brand-800/70">
            <tr>
                <th class="px-4 py-3 font-semibold">Document</th>
                <th class="px-4 py-3 font-semibold">Formats</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold">Time</th>
                <th class="px-4 py-3 font-semibold">Created</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-brand-900/5">
            @forelse ($logs as $log)
                <tr>
                    <td class="px-4 py-3">
                        @if ($log->document)
                            <a href="{{ route('admin.documents.show', $log->document) }}" class="font-medium text-brand-950 hover:text-accent-700">{{ $log->document->original_name }}</a>
                            @if ($log->document->user)
                                <div class="text-xs text-brand-800/60">{{ $log->document->user->email }}</div>
                            @endif
                        @else
                            <span class="text-brand-800/60">#{{ $log->document_id }}</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">{{ $log->source_format }} → {{ $log->target_format }}</td>
                    <td class="px-4 py-3">
                        <span class="{{ $log->status === 'success' ? 'text-success-800' : 'text-danger-800' }}">{{ $log->status }}</span>
                        @if ($log->error_message)
                            <div class="mt-1 max-w-xs truncate text-xs text-danger-700" title="{{ $log->error_message }}">{{ $log->error_message }}</div>
                        @endif
                    </td>
                    <td class="px-4 py-3 tabular-nums text-brand-800/80">{{ $log->processing_time_ms }} ms</td>
                    <td class="px-4 py-3 text-brand-800/70">{{ $log->created_at?->format('Y-m-d H:i') }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-brand-800/70">No conversion logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
