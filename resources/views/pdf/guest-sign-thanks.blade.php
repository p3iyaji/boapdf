@extends('layouts.app')

@section('title', 'Signature received')

@section('content')
<div class="flex min-h-screen items-center justify-center px-4 py-12">
    <div class="w-full max-w-md rounded-2xl border border-brand-900/10 bg-canvas-50/95 p-8 text-center shadow-2xl shadow-brand-950/20 backdrop-blur-md">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700">
            <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>
        <h1 class="font-display text-2xl font-bold tracking-tight text-brand-950">Thank you</h1>
        <p class="mt-2 text-sm text-brand-800/80">
            {{ session('success', 'Your signature has been applied to the document. You can close this page.') }}
        </p>
        <p class="mt-6 text-xs text-brand-800/55">
            @include('partials.legal-links', ['linkClass' => 'hover:text-brand-900'])
        </p>
    </div>
</div>
@endsection
