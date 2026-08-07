@extends('layouts.app')

@section('title', 'Edit '.$user->name.' - Admin - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6">
    <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Edit user</h1>
    <p class="mt-1 text-sm text-brand-900/70">{{ $user->email }}</p>
</div>

<form method="POST" action="{{ route('admin.users.update', $user) }}" class="max-w-xl space-y-4 rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-sm sm:p-6">
    @csrf
    @method('PUT')
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-brand-900">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="email" class="mb-1 block text-sm font-medium text-brand-900">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="password" class="mb-1 block text-sm font-medium text-brand-900">New password</label>
        <input id="password" name="password" type="password"
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
        <p class="mt-1 text-xs text-brand-800/60">Leave blank to keep the current password.</p>
    </div>
    <div>
        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-brand-900">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password"
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div class="flex flex-col gap-2 sm:flex-row sm:gap-6">
        <label class="inline-flex items-center gap-2 text-sm text-brand-900">
            <input type="hidden" name="is_admin" value="0">
            <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin', $user->is_admin)) class="rounded border-brand-900/30 text-brand-900 focus:ring-accent-400">
            Administrator
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-brand-900">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $user->is_active)) class="rounded border-brand-900/30 text-brand-900 focus:ring-accent-400">
            Active
        </label>
    </div>
    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-semibold text-accent-50 hover:bg-brand-800">Save</button>
        <a href="{{ route('admin.users.show', $user) }}" class="rounded-lg border border-brand-900/15 px-4 py-2.5 text-sm font-medium text-brand-900 hover:bg-brand-50">Cancel</a>
    </div>
</form>
@endsection
