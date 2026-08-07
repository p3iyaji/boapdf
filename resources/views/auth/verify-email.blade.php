@extends('layouts.app')

@section('title', 'Verify your email - '.config('app.name'))

@section('content')
<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-md rounded-2xl border border-brand-900/10 bg-canvas-50/95 p-8 shadow-2xl shadow-brand-950/20 backdrop-blur-md">
        <div class="mb-8 flex flex-col items-center text-center">
            <div class="mb-4 rounded-2xl bg-brand-950/5 p-2 ring-1 ring-brand-900/10" aria-hidden="true">
                <x-boa-theme::mark size="lg" />
            </div>
            <h1 class="font-display text-3xl font-bold tracking-tight text-brand-950">Verify your email</h1>
            <p class="mt-2 text-sm leading-relaxed text-brand-800/70">
                We sent a verification link to
                <span class="font-semibold text-brand-950">{{ auth()->user()->email }}</span>.
                Open that link to activate your account.
            </p>
        </div>

        @if (session('status'))
            <div class="mb-4 rounded-xl border border-brand-200/80 bg-brand-50/95 px-4 py-3 text-sm text-brand-950">
                {{ session('status') }}
            </div>
        @endif

        <form method="POST" action="{{ route('verification.send') }}" class="space-y-4">
            @csrf
            <button type="submit"
                class="w-full rounded-lg bg-gradient-to-r from-brand-800 to-brand-950 py-2.5 font-semibold text-accent-50 shadow-lg shadow-brand-950/30 transition hover:from-brand-700 hover:to-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2">
                Resend verification email
            </button>
        </form>

        <form method="POST" action="{{ route('logout') }}" class="mt-4">
            @csrf
            <button type="submit" class="w-full rounded-lg border border-brand-900/15 bg-white py-2.5 text-sm font-semibold text-brand-950 transition hover:bg-brand-50">
                Sign out
            </button>
        </form>
    </div>
</div>
@endsection
