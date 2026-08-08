{{-- Shared links for Terms of Use and Privacy Policy --}}
@php
    $linkClass = $linkClass ?? 'underline decoration-brand-400/40 underline-offset-2 transition hover:text-accent-600 hover:decoration-accent-500';
@endphp
<a href="{{ route('legal.terms') }}" class="{{ $linkClass }}">Terms of Use</a>
<span class="mx-1.5 opacity-40" aria-hidden="true">·</span>
<a href="{{ route('legal.privacy') }}" class="{{ $linkClass }}">Privacy Policy</a>
