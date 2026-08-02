@extends('layouts.app')

@section('title', 'Create your account - '.config('app.name'))

@section('content')
<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-md rounded-2xl border border-brand-900/10 bg-canvas-50/95 p-8 shadow-2xl shadow-brand-950/20 backdrop-blur-md">
        <div class="mb-8 flex flex-col items-center text-center">
            <div class="mb-4 rounded-2xl bg-brand-950/5 p-2 ring-1 ring-brand-900/10" aria-hidden="true">
                <x-boa-theme::mark size="lg" />
            </div>
            <h1 class="font-display text-3xl font-bold tracking-tight text-brand-950">Create your account</h1>
            <p class="mt-1 text-sm text-brand-800/70">Begin your journey with documents in light</p>
        </div>

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-danger-200/90 bg-danger-50/95 px-4 py-3 text-danger-900">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('register.store') }}" class="space-y-5">
            @csrf
            <div>
                <label for="name" class="mb-1 block text-sm font-medium text-brand-950">Name</label>
                <input type="text" id="name" name="name" value="{{ old('name') }}" required
                    class="w-full rounded-lg border border-brand-900/15 bg-white px-3 py-2 text-canvas-900 shadow-inner shadow-brand-950/5 focus:outline-none focus:ring-2 focus:ring-accent-500/80">
            </div>
            <div>
                <label for="email" class="mb-1 block text-sm font-medium text-brand-950">Email</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required
                    class="w-full rounded-lg border border-brand-900/15 bg-white px-3 py-2 text-canvas-900 shadow-inner shadow-brand-950/5 focus:outline-none focus:ring-2 focus:ring-accent-500/80">
            </div>
            <div>
                <label for="password" class="mb-1 block text-sm font-medium text-brand-950">Password</label>
                <input type="password" id="password" name="password" required
                    class="w-full rounded-lg border border-brand-900/15 bg-white px-3 py-2 text-canvas-900 shadow-inner shadow-brand-950/5 focus:outline-none focus:ring-2 focus:ring-accent-500/80">
            </div>
            <div>
                <label for="password_confirmation" class="mb-1 block text-sm font-medium text-brand-950">Confirm password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" required
                    class="w-full rounded-lg border border-brand-900/15 bg-white px-3 py-2 text-canvas-900 shadow-inner shadow-brand-950/5 focus:outline-none focus:ring-2 focus:ring-accent-500/80">
            </div>
            <button type="submit" class="w-full rounded-lg bg-gradient-to-r from-brand-800 to-brand-950 py-2.5 font-semibold text-accent-50 shadow-lg shadow-brand-950/30 transition hover:from-brand-700 hover:to-brand-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-accent-400 focus-visible:ring-offset-2">
                Create account
            </button>
            <p class="text-center text-sm text-brand-900/70">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-accent-700 hover:text-accent-600">Sign in</a>
            </p>
        </form>
    </div>
</div>
@endsection
