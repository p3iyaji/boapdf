@extends('layouts.app')

@section('title', config('app.name').' — Document tools that hold fast')

@push('head')
<style>
    @keyframes home-fade-up {
        from { opacity: 0; transform: translateY(1.25rem); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes home-bg-drift {
        from { transform: scale(1.12) translate3d(-1.5%, 0, 0); }
        to { transform: scale(1.18) translate3d(1.5%, -1%, 0); }
    }
    @keyframes home-fade-in {
        from { opacity: 0; }
        to { opacity: 1; }
    }
    .home-fade-up {
        animation: home-fade-up 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
    }
    .home-fade-up-delay {
        animation: home-fade-up 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.15s both;
    }
    .home-fade-up-delay-2 {
        animation: home-fade-up 0.9s cubic-bezier(0.22, 1, 0.36, 1) 0.28s both;
    }
    .home-bg-drift {
        animation: home-bg-drift 28s ease-in-out infinite alternate;
    }
    .home-section-in {
        animation: home-fade-in 1s ease both;
    }
    @media (prefers-reduced-motion: reduce) {
        .home-fade-up,
        .home-fade-up-delay,
        .home-fade-up-delay-2,
        .home-bg-drift,
        .home-section-in {
            animation: none !important;
        }
    }
</style>
@endpush

@section('content')
<div class="relative min-h-screen overflow-hidden bg-gradient-to-br from-brand-900 via-brand-950 to-canvas-950">
    {{-- Full-bleed boa atmosphere across the whole page — visible but soft --}}
    <div class="pointer-events-none fixed inset-0 z-0 overflow-hidden" aria-hidden="true">
        <img
            src="{{ asset('images/boa-constrictor.jpg') }}"
            alt=""
            class="home-bg-drift absolute inset-0 h-full w-full scale-110 object-cover object-center opacity-[0.28] blur-[1px] contrast-110 saturate-[0.55] brightness-90"
        >
        <div class="absolute inset-0 bg-gradient-to-b from-brand-950/75 via-brand-900/70 to-canvas-950/80"></div>
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_30%_20%,rgba(15,118,110,0.22),transparent_55%)]"></div>
    </div>

    <header class="relative z-10 mx-auto flex max-w-5xl items-center justify-between px-6 py-5 sm:px-8">
        <a href="{{ route('home') }}" class="flex min-w-0 items-center gap-3 transition hover:opacity-90">
            <x-boa-theme::mark size="md" class="h-10 w-10 sm:h-11 sm:w-11" />
            <span class="truncate font-display text-lg font-semibold tracking-wide text-accent-50 sm:text-xl">
                {{ config('app.name') }}
            </span>
        </a>
        <nav class="flex items-center gap-3 text-sm sm:gap-4">
            @auth
                <a href="{{ route('dashboard') }}" class="font-semibold text-accent-100 transition hover:text-accent-300">Dashboard</a>
            @else
                <a href="{{ route('login') }}" class="font-medium text-brand-100/85 transition hover:text-accent-50">Sign in</a>
                <a href="{{ route('register') }}" class="rounded-lg bg-accent-500 px-3.5 py-2 font-semibold text-brand-950 transition hover:bg-accent-400">Get started</a>
            @endauth
        </nav>
    </header>

    <section class="relative z-10 mx-auto flex min-h-[calc(100dvh-4.5rem)] max-w-5xl flex-col justify-center px-6 pb-20 pt-10 sm:px-8 sm:pb-28">
        <div class="home-fade-up mb-6">
            <x-boa-theme::mark size="xl" class="h-20 w-20 drop-shadow-lg sm:h-24 sm:w-24" />
        </div>
        <p class="home-fade-up font-display text-5xl font-bold tracking-tight text-accent-50 sm:text-6xl md:text-7xl">
            {{ config('app.name') }}
        </p>
        <h1 class="home-fade-up-delay mt-5 max-w-2xl text-balance text-2xl font-semibold leading-snug text-brand-100 sm:text-3xl md:text-4xl">
            Hold your documents steady—from upload to signature.
        </h1>
        <p class="home-fade-up-delay-2 mt-4 max-w-xl text-base leading-relaxed text-brand-200/85 sm:text-lg">
            A focused workspace to store, merge, compress, and sign PDFs without the clutter.
        </p>
        <div class="home-fade-up-delay-2 mt-8 flex flex-wrap items-center gap-3">
            @auth
                <a href="{{ route('dashboard') }}"
                    class="inline-flex min-h-11 items-center rounded-lg bg-accent-500 px-5 py-2.5 text-sm font-semibold text-brand-950 shadow-lg shadow-black/30 transition hover:bg-accent-400">
                    Open dashboard
                </a>
                <a href="{{ route('pdf.index') }}"
                    class="inline-flex min-h-11 items-center rounded-lg border border-brand-200/25 bg-brand-950/40 px-5 py-2.5 text-sm font-semibold text-accent-50 backdrop-blur-sm transition hover:bg-brand-900/55">
                    My PDFs
                </a>
            @else
                <a href="{{ route('register') }}"
                    class="inline-flex min-h-11 items-center rounded-lg bg-accent-500 px-5 py-2.5 text-sm font-semibold text-brand-950 shadow-lg shadow-black/30 transition hover:bg-accent-400">
                    Create account
                </a>
                <a href="{{ route('login') }}"
                    class="inline-flex min-h-11 items-center rounded-lg border border-brand-200/25 bg-brand-950/40 px-5 py-2.5 text-sm font-semibold text-accent-50 backdrop-blur-sm transition hover:bg-brand-900/55">
                    Sign in
                </a>
            @endauth
        </div>
    </section>

    <section class="relative z-10 border-t border-brand-200/10 bg-brand-950/35 backdrop-blur-[1px]">
        <div class="home-section-in mx-auto max-w-5xl px-6 py-16 sm:px-8 sm:py-20">
            <h2 class="font-display text-2xl font-bold tracking-tight text-accent-50 sm:text-3xl">What you can do</h2>
            <p class="mt-3 max-w-2xl text-base leading-relaxed text-brand-200/80">
                Keep a private library of PDFs, then shape them when you need to—combine files, shrink heavy scans, convert for sharing, or collect signatures in one place.
            </p>
            <ul class="mt-10 grid gap-8 sm:grid-cols-2">
                <li>
                    <h3 class="font-semibold text-accent-100">Library &amp; upload</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-brand-200/70">Bring PDFs in from your device or camera, browse what you own, and download when you are ready.</p>
                </li>
                <li>
                    <h3 class="font-semibold text-accent-100">Merge &amp; compress</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-brand-200/70">Join multiple documents into one clean file, or compress oversized PDFs for easier sending.</p>
                </li>
                <li>
                    <h3 class="font-semibold text-accent-100">Convert</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-brand-200/70">Turn PDFs into formats you can edit or share—text, images, and more when your tools allow.</p>
                </li>
                <li>
                    <h3 class="font-semibold text-accent-100">Sign &amp; invite</h3>
                    <p class="mt-1.5 text-sm leading-relaxed text-brand-200/70">Draw or type a signature yourself, or invite others to sign with a secure link.</p>
                </li>
            </ul>
        </div>
    </section>

    <footer class="relative z-10 border-t border-brand-200/10 px-6 py-8 text-center text-sm text-brand-300/60 sm:px-8">
        &copy; {{ date('Y') }} {{ config('app.name') }}
    </footer>
</div>
@endsection
