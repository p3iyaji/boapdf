@extends('layouts.app')

@section('title', 'Audit logs - Admin - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6">
    <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Audit logs</h1>
    <p class="mt-1 text-sm text-brand-900/70">Complete activity trail across the application, including account deletions.</p>
</div>

<form method="GET" action="{{ route('admin.audit-logs.index') }}" class="mb-4 flex flex-col gap-3 rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm sm:flex-row sm:items-end">
    <div class="min-w-0 flex-1">
        <label for="q" class="mb-1 block text-xs font-medium text-brand-800/80">Search</label>
        <input id="q" type="search" name="q" value="{{ $search }}" placeholder="Actor, action, description, or IP"
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="action" class="mb-1 block text-xs font-medium text-brand-800/80">Action</label>
        <select id="action" name="action" class="rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm">
            <option value="">Any</option>
            @foreach ($actions as $actionOption)
                <option value="{{ $actionOption }}" @selected($action === $actionOption)>{{ $actionOption }}</option>
            @endforeach
        </select>
    </div>
    <button type="submit" class="rounded-lg border border-brand-900/15 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-950 hover:bg-brand-100">Filter</button>
</form>

<div class="overflow-x-auto rounded-xl border border-brand-900/10 bg-white/95 shadow-sm">
    <table class="min-w-full divide-y divide-brand-900/10 text-left text-sm">
        <thead class="bg-brand-50/80 text-xs uppercase tracking-wide text-brand-800/70">
            <tr>
                <th class="px-4 py-3 font-semibold">When</th>
                <th class="px-4 py-3 font-semibold">Actor</th>
                <th class="px-4 py-3 font-semibold">Action</th>
                <th class="px-4 py-3 font-semibold">Details</th>
                <th class="px-4 py-3 font-semibold">IP</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-brand-900/5">
            @forelse ($logs as $log)
                <tr class="{{ str_contains($log->action, 'deleted') || $log->action === 'account.deleted' ? 'bg-danger-50/40' : '' }}">
                    <td class="whitespace-nowrap px-4 py-3 text-brand-800/70">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="px-4 py-3">
                        <div class="font-medium text-brand-950">{{ $log->actor_name ?? $log->user?->name ?? 'Unknown' }}</div>
                        <div class="text-xs text-brand-800/60">{{ $log->actor_email ?? $log->user?->email }}</div>
                    </td>
                    <td class="px-4 py-3">
                        <code class="rounded bg-brand-50 px-1.5 py-0.5 text-xs text-brand-900">{{ $log->action }}</code>
                    </td>
                    <td class="max-w-md px-4 py-3 text-brand-900/80">
                        <div>{{ $log->description }}</div>
                        @if (! empty($log->metadata))
                            <details class="mt-1 text-xs text-brand-800/70">
                                <summary class="cursor-pointer select-none">Metadata</summary>
                                <pre class="mt-1 overflow-x-auto whitespace-pre-wrap break-all rounded bg-brand-50/80 p-2">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                            </details>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-4 py-3 tabular-nums text-brand-800/70">{{ $log->ip_address }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-brand-800/70">No audit logs found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $logs->links() }}</div>
@endsection
