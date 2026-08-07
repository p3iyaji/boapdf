@extends('layouts.app')

@section('title', 'Create user - '.config('app.name'))

@section('content')
@include('admin.partials.nav')

<div class="mb-6">
    <h1 class="font-display text-2xl font-bold text-brand-950 sm:text-3xl">Create user</h1>
</div>

<form method="POST" action="{{ route('admin.users.store') }}" class="max-w-xl space-y-4 rounded-xl border border-brand-900/10 bg-white/95 p-5 shadow-sm sm:p-6">
    @csrf
    <div>
        <label for="name" class="mb-1 block text-sm font-medium text-brand-900">Name</label>
        <input id="name" name="name" type="text" value="{{ old('name') }}" required
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="email" class="mb-1 block text-sm font-medium text-brand-900">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="password" class="mb-1 block text-sm font-medium text-brand-900">Password</label>
        <input id="password" name="password" type="password" required
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div>
        <label for="password_confirmation" class="mb-1 block text-sm font-medium text-brand-900">Confirm password</label>
        <input id="password_confirmation" name="password_confirmation" type="password" required
            class="w-full rounded-lg border border-brand-900/15 px-3 py-2 text-sm shadow-sm focus:border-accent-500 focus:outline-none focus:ring-2 focus:ring-accent-400/40">
    </div>
    <div class="flex flex-col gap-2 sm:flex-row sm:gap-6">
        <label class="inline-flex items-center gap-2 text-sm text-brand-900">
            <input type="checkbox" name="is_admin" value="1" @checked(old('is_admin')) class="rounded border-brand-900/30 text-brand-900 focus:ring-accent-400">
            Administrator
        </label>
        <label class="inline-flex items-center gap-2 text-sm text-brand-900">
            <input type="hidden" name="is_active" value="0">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', true)) class="rounded border-brand-900/30 text-brand-900 focus:ring-accent-400">
            Active
        </label>
    </div>
    <div class="flex gap-3 pt-2">
        <button type="submit" class="rounded-lg bg-brand-900 px-4 py-2.5 text-sm font-semibold text-accent-50 hover:bg-brand-800">Create</button>
        <a href="{{ route('admin.users.index') }}" class="rounded-lg border border-brand-900/15 px-4 py-2.5 text-sm font-medium text-brand-900 hover:bg-brand-50">Cancel</a>
    </div>
</form>
@endsection
