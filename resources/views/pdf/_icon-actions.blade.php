{{-- Icon-only View / Download / Delete for My PDFs. Expects $doc; optional $large for touch targets. --}}
@php
    $btn = ($large ?? false)
        ? 'inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2'
        : 'inline-flex min-h-9 min-w-9 items-center justify-center rounded-lg transition focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:ring-offset-2';
    $icon = 'h-5 w-5 shrink-0';
@endphp
<a href="{{ route('pdf.show', $doc) }}"
   class="{{ $btn }} bg-blue-600 text-white shadow-sm hover:bg-blue-700 active:bg-blue-800"
   title="View"
   aria-label="View {{ $doc->original_name }}">
    <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
    </svg>
</a>
<a href="{{ route('pdf.download', $doc) }}"
   class="{{ $btn }} border border-gray-300 bg-white text-gray-800 shadow-sm hover:bg-gray-50 active:bg-gray-100"
   title="Download"
   aria-label="Download {{ $doc->original_name }}">
    <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
    </svg>
</a>
<form method="POST" action="{{ route('pdf.destroy', $doc) }}" class="inline-flex" onsubmit="return confirm('Delete this document?')">
    @csrf
    @method('DELETE')
    <button type="submit"
            class="{{ $btn }} border border-red-200 bg-red-50 text-red-700 hover:bg-red-100 active:bg-red-200"
            title="Delete"
            aria-label="Delete {{ $doc->original_name }}">
        <svg class="{{ $icon }}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
    </button>
</form>
