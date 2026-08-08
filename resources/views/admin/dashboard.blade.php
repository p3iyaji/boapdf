@extends('layouts.app')

@section('title', 'Admin - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6 md:mb-8">
    <h1 class="font-display text-2xl font-bold tracking-tight text-brand-950 sm:text-3xl">Administration</h1>
    <p class="mt-2 text-sm text-brand-900/70 sm:text-base">Manage users, documents, signatures, conversions, and full audit activity.</p>
</div>

<div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
    <a href="{{ route('admin.users.index') }}" class="rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-md shadow-brand-950/5 transition hover:border-accent-400/40">
        <p class="text-xs text-brand-800/70 sm:text-sm">Users</p>
        <p class="mt-1 text-3xl font-bold tabular-nums text-brand-950">{{ $stats['users'] }}</p>
        <p class="mt-2 text-xs text-brand-800/60">{{ $stats['users_inactive'] }} inactive · {{ $stats['users_trashed'] }} deleted</p>
    </a>
    <a href="{{ route('admin.documents.index') }}" class="rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-md shadow-brand-950/5 transition hover:border-accent-400/40">
        <p class="text-xs text-brand-800/70 sm:text-sm">Documents</p>
        <p class="mt-1 text-3xl font-bold tabular-nums text-brand-950">{{ $stats['documents'] }}</p>
        <p class="mt-2 text-xs text-brand-800/60">{{ $stats['documents_failed'] }} failed</p>
    </a>
    <a href="{{ route('admin.signature-requests.index') }}" class="rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-md shadow-brand-950/5 transition hover:border-accent-400/40">
        <p class="text-xs text-brand-800/70 sm:text-sm">Signatures</p>
        <p class="mt-1 text-3xl font-bold tabular-nums text-brand-950">{{ $stats['signature_pending'] + $stats['signature_signed'] }}</p>
        <p class="mt-2 text-xs text-brand-800/60">{{ $stats['signature_pending'] }} pending · {{ $stats['signature_signed'] }} signed</p>
    </a>
    <a href="{{ route('admin.conversion-logs.index') }}" class="rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-md shadow-brand-950/5 transition hover:border-accent-400/40">
        <p class="text-xs text-brand-800/70 sm:text-sm">Conversion logs</p>
        <p class="mt-1 text-3xl font-bold tabular-nums text-brand-950">{{ $stats['conversion_logs'] }}</p>
        <p class="mt-2 text-xs text-brand-800/60">{{ $stats['conversion_failures'] }} non-success</p>
    </a>
    <a href="{{ route('admin.audit-logs.index') }}" class="rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-md shadow-brand-950/5 transition hover:border-accent-400/40">
        <p class="text-xs text-brand-800/70 sm:text-sm">Audit logs</p>
        <p class="mt-1 text-3xl font-bold tabular-nums text-brand-950">{{ $stats['audit_logs'] }}</p>
        <p class="mt-2 text-xs text-brand-800/60">{{ $stats['account_deletions'] }} account deletions</p>
    </a>
</div>
@endsection
