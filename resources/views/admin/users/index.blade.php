@extends('layouts.app')

@section('title', 'Admin users - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Users</h1>
        <p class="mt-1 text-sm text-brand-900/70">Create, deactivate, or soft-delete accounts.</p>
    </div>
    <a href="{{ route('admin.users.create') }}"
        class="inline-flex items-center justify-center rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-semibold text-accent-50 shadow hover:bg-brand-800">
        New user
    </a>
</div>

<form method="GET" action="{{ route('admin.users.index') }}" class="mb-4 flex flex-col gap-3 rounded-xl border border-brand-900/10 bg-white/95 p-4 shadow-sm sm:flex-row sm:items-end">
    <div class="min-w-0 flex-1">
        <label for="q" class="mb-1 block text-xs font-medium text-brand-800/80">Search</label>
        <input id="q" type="search" name="q" value="{{ $search }}" placeholder="Name or email"
            class="w-full rounded-lg border border-brand-900/15 bg-white px-3 py-2 text-sm text-brand-950 shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="status" class="mb-1 block text-xs font-medium text-brand-800/80">Filter</label>
        <select id="status" name="status" class="rounded-lg border border-brand-900/15 bg-white px-3 py-2 text-sm text-brand-950 shadow-sm">
            <option value="" @selected($status === '')>All active records</option>
            <option value="inactive" @selected($status === 'inactive')>Inactive</option>
            <option value="admin" @selected($status === 'admin')>Admins</option>
            <option value="trashed" @selected($status === 'trashed')>Soft-deleted</option>
        </select>
    </div>
    <button type="submit" class="rounded-lg border border-brand-900/15 bg-brand-50 px-4 py-2 text-sm font-semibold text-brand-950 hover:bg-brand-100">Filter</button>
</form>

<div class="overflow-x-auto rounded-xl border border-brand-900/10 bg-white/95 shadow-sm">
    <table class="min-w-full divide-y divide-brand-900/10 text-left text-sm">
        <thead class="bg-brand-50/80 text-xs uppercase tracking-wide text-brand-800/70">
            <tr>
                <th class="px-4 py-3 font-semibold">User</th>
                <th class="px-4 py-3 font-semibold">Role</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold">Docs</th>
                <th class="px-4 py-3 font-semibold">Joined</th>
                <th class="px-4 py-3 font-semibold"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-brand-900/5">
            @forelse ($users as $user)
                <tr>
                    <td class="px-4 py-3">
                        <div class="font-medium text-brand-950">{{ $user->name }}</div>
                        <div class="text-xs text-brand-800/70">{{ $user->email }}</div>
                    </td>
                    <td class="px-4 py-3">
                        @if ($user->is_admin)
                            <span class="rounded-md bg-accent-100 px-2 py-0.5 text-xs font-semibold text-accent-950">Admin</span>
                        @else
                            <span class="text-brand-800/70">User</span>
                        @endif
                    </td>
                    <td class="px-4 py-3">
                        @if ($user->trashed())
                            <span class="rounded-md bg-danger-100 px-2 py-0.5 text-xs font-semibold text-danger-900">Deleted</span>
                        @elseif (! $user->is_active)
                            <span class="rounded-md bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-950">Inactive</span>
                        @else
                            <span class="rounded-md bg-success-100 px-2 py-0.5 text-xs font-semibold text-success-950">Active</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 tabular-nums text-brand-900">{{ $user->documents_count }}</td>
                    <td class="px-4 py-3 text-brand-800/70">{{ $user->created_at?->format('Y-m-d') }}</td>
                    <td class="px-4 py-3 text-right">
                        @if ($user->trashed())
                            <form method="POST" action="{{ route('admin.users.restore', $user->id) }}" class="inline">
                                @csrf
                                <button type="submit" class="text-sm font-semibold text-brand-900 hover:text-accent-700">Restore</button>
                            </form>
                        @else
                            <a href="{{ route('admin.users.show', $user) }}" class="text-sm font-semibold text-brand-900 hover:text-accent-700">View</a>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-brand-800/70">No users found.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">{{ $users->links() }}</div>
@endsection
