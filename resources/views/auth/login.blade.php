@extends('layouts.app')

@section('title', 'Sign in - '.config('app.name'))

@section('content')
<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-md rounded-2xl border border-brand-900/10 bg-canvas-50/95 p-8 shadow-2xl shadow-brand-950/20 backdrop-blur-md">
        <div class="mb-8 flex flex-col items-center text-center">
            <div class="mb-4 rounded-2xl bg-brand-950/5 p-2 ring-1 ring-brand-900/10" aria-hidden="true">
                <x-boa-theme::mark size="lg" />
            </div>
            <h1 class="font-display text-3xl font-bold tracking-tight text-brand-950">{{ config('app.name') }}</h1>
            <p class="mt-1 text-sm text-brand-800/70">Sign in to open your library of PDFs</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-danger-200/90 bg-danger-50/95 px-4 py-3 text-danger-900">
                @foreach ($errors->all() as $error)
                    <p>{{ $error }}</p>
                @endforeach
            </div>
        @endif

        @if (session('success'))
            <div class="mb-4 rounded-xl border border-brand-200/80 bg-brand-50/95 px-4 py-3 text-brand-950">
                {{ session('success') }}
            </div>
        @endif

        <form method="POST" action="{{ route('authenticate') }}" class="space-y-5">
            @csrf
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-brand-950">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
                    class="w-full rounded-lg border border-brand-900/15 bg-white px-3 py-2 text-canvas-900 shadow-inner shadow-brand-950/5 focus:outline-none focus:ring-2 focus:ring-accent-500/80">
            </div>
            <div>
                <div class="mb-1 flex items-center justify-between gap-2">
                    <label for="password" class="block text-sm font-medium text-brand-950">Password</label>
                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-accent-700 hover:text-accent-600">Forgot password?</a>
                </div>
                <input type="password" id="password" name="password" required
                    class="w-full rounded-lg border border-brand-900/15 bg-white px-3 py-2 text-canvas-900 shadow-inner shadow-brand-950/5 focus:outline-none focus:ring-2 focus:ring-accent-500/80">
            </div>
            <label class="flex items-center text-sm text-brand-900/75">
                <input type="checkbox" name="remember" class="mr-2 rounded border-brand-800/30 text-accent-600 focus:ring-accent-500">
                Remember me
            </label>
            <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-brand-800 to-brand-950 py-2.5 font-semibold text-accent-50 shadow-lg shadow-brand-950/30 transition hover:from-brand-700 hover:to-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2">
                Sign in
            </button>
            <p class="text-center text-sm text-brand-900/70">
                Don't have an account?
                <a href="{{ route('register') }}" class="font-semibold text-accent-700 hover:text-accent-600">Register</a>
            </p>
        </form>
    </div>
</div>
@endsection
