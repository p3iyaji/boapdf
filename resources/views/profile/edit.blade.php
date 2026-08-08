@extends('layouts.app')

@section('title', 'Profile - '.config('app.name'))

@section('content')
<div class="mb-6 md:mb-8">
    <h1 class="font-display text-2xl font-bold tracking-tight text-brand-950 sm:text-3xl">Your profile</h1>
    <p class="mt-2 text-sm text-brand-900/70 sm:text-base">Manage your account details, password, and data.</p>
</div>

<div class="space-y-6">
    <form method="POST" action="{{ route('profile.update') }}" class="max-w-xl space-y-4 rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-sm sm:p-6">
        @csrf
        @method('PUT')
        <h2 class="font-display text-lg font-semibold text-brand-950">Account details</h2>
        <div>
            <label for="name" class="mb-1 block text-sm font-medium text-brand-900">Name</label>
            <input id="name" name="name" type="text" value="{{ old('name', $user->name) }}" required autocomplete="name"
                class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
        </div>
        <div>
            <label for="email" class="mb-1 block text-sm font-medium text-brand-900">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $user->email) }}" required autocomplete="email"
                class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
            <p class="mt-1 text-xs text-brand-800/60">Changing your email will require re-verification.</p>
        </div>
        <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-semibold text-accent-50 hover:bg-brand-800">
            Save profile
        </button>
    </form>

    <form method="POST" action="{{ route('profile.password') }}" id="password" class="max-w-xl space-y-4 rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-sm sm:p-6">
        @csrf
        @method('PUT')
        <h2 class="font-display text-lg font-semibold text-brand-950">Change password</h2>
        <div>
            <label for="current_password" class="mb-1 block text-sm font-medium text-brand-900">Current password</label>
            <input id="current_password" name="current_password" type="password" required autocomplete="current-password"
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
        <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-semibold text-accent-50 hover:bg-brand-800">
            Update password
        </button>
    </form>

    <div class="max-w-xl space-y-4 rounded-xl border border-danger-300/60 bg-danger-50/80 p-5 shadow-sm sm:p-6">
        <h2 class="font-display text-lg font-semibold text-danger-900">Delete account</h2>
        <p class="text-sm leading-relaxed text-danger-900/80">
            Permanently delete your account and personal documents. This supports your right to erasure.
            Administrators retain an anonymized audit record of the deletion for accountability.
            This cannot be undone.
        </p>
        <form method="POST" action="{{ route('profile.destroy') }}" class="space-y-4"
            onsubmit="return confirm('Delete your account and personal data permanently? This cannot be undone.');">
            @csrf
            @method('DELETE')
            <div>
                <label for="delete_password" class="mb-1 block text-sm font-medium text-danger-950">Confirm with your password</label>
                <input id="delete_password" name="password" type="password" required autocomplete="current-password"
                    class="w-full rounded-lg border border-danger-300/70 bg-white px-3 py-2 text-sm shadow-sm focus:border-danger-500 focus:outline-none focus:ring-2 focus:ring-danger-400/40">
            </div>
            <div>
                <label for="confirmation" class="mb-1 block text-sm font-medium text-danger-950">Type DELETE to confirm</label>
                <input id="confirmation" name="confirmation" type="text" required autocomplete="off"
                    class="w-full rounded-lg border border-danger-300/70 bg-white px-3 py-2 text-sm shadow-sm focus:border-danger-500 focus:outline-none focus:ring-2 focus:ring-danger-400/40"
                    placeholder="DELETE">
            </div>
            <button type="submit" class="rounded-lg bg-danger-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-danger-800">
                Delete my account
            </button>
        </form>
    </div>
</div>
@endsection
