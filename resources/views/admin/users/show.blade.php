@extends('layouts.app')

@section('title', $user->name.' - Admin - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
    <div>
        <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">{{ $user->name }}</h1>
        <p class="mt-1 text-sm text-brand-900/70">{{ $user->email }}</p>
    </div>
    <div class="flex flex-wrap gap-2">
        <a href="{{ route('admin.users.edit', $user) }}" class="rounded-lg border border-brand-900/15 bg-white px-4 py-2 text-sm font-semibold text-brand-950 hover:bg-brand-50">Edit</a>
        @if ($user->is_active)
            <form method="POST" action="{{ route('admin.users.activation', $user) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="activate" value="0">
                <button type="submit" class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-2 text-sm font-semibold text-amber-950 hover:bg-amber-100">Deactivate</button>
            </form>
        @else
            <form method="POST" action="{{ route('admin.users.activation', $user) }}">
                @csrf
                @method('PATCH')
                <input type="hidden" name="activate" value="1">
                <button type="submit" class="rounded-lg border border-success-300 bg-success-50 px-4 py-2 text-sm font-semibold text-success-950 hover:bg-success-100">Activate</button>
            </form>
        @endif
        <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Soft-delete this user?')">
            @csrf
            @method('DELETE')
            <button type="submit" class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-2 text-sm font-semibold text-danger-900 hover:bg-danger-100">Soft delete</button>
        </form>
    </div>
</div>

<div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3">
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Role</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $user->is_admin ? 'Administrator' : 'User' }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Status</p>
        <p class="mt-1 font-semibold text-brand-950">{{ $user->is_active ? 'Active' : 'Inactive' }}</p>
    </div>
    <div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm">
        <p class="text-xs text-brand-800/70">Documents</p>
        <p class="mt-1 font-semibold tabular-nums text-brand-950">{{ $user->documents_count }}</p>
    </div>
</div>

<div class="rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm sm:p-5">
    <h2 class="mb-3 text-lg font-semibold text-brand-950">Recent documents</h2>
    <ul class="divide-y divide-brand-900/5">
        @forelse ($recentDocuments as $document)
            <li class="flex items-center justify-between gap-3 py-2 text-sm">
                <div class="min-w-0">
                    <a href="{{ route('admin.documents.show', $document) }}" class="truncate font-medium text-brand-950 hover:text-accent-700">{{ $document->original_name }}</a>
                    <p class="text-xs text-brand-800/60">{{ $document->operation_type }} · {{ $document->status }}</p>
                </div>
                <span class="shrink-0 text-xs text-brand-800/60">{{ $document->created_at?->format('Y-m-d') }}</span>
            </li>
        @empty
            <li class="py-4 text-sm text-brand-800/70">No documents yet.</li>
        @endforelse
    </ul>
</div>
@endsection
