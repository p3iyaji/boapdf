@extends('layouts.app')

@section('title', 'Change password - Admin - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6">
    <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Change password</h1>
    <p class="mt-1 text-sm text-brand-900/70">Update the password for your admin account.</p>
</div>

<form method="POST" action="{{ route('admin.password.update') }}" class="max-w-xl space-y-4 rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-sm sm:p-6">
    @csrf
    @method('PUT')

    <div>
        <label for="current_password" class="mb-1 block text-sm font-medium text-brand-900">Current password</label>
        <input id="current_password" name="current_password" type="password" required autocomplete="current-password" autofocus
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="password" class="mb-1 block text-sm font-medium text-brand-900">New password</label>
        <input id="password" name="password" type="password" required autocomplete="new-password"
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-brand-900">Confirm new password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-semibold text-accent-50 hover:bg-brand-800">
            Update password
        </button>
        <a href="{{ route('admin.dashboard') }}" class="rounded-lg border border-brand-900/15 px-4 py-2.5 text-sm font-medium text-brand-900 hover:bg-brand-50">
            Cancel
        </a>
    </div>
</form>
@endsection
