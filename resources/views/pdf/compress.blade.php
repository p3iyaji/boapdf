@extends('layouts.app')

@section('title', 'Compress PDF - '.config('app.name'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Compress PDF</h1>
    <p class="text-gray-600 mt-1">Pick a level. With Ghostscript, we re-encode embedded images (not just pass-through JPEGs) so photo and scan PDFs usually shrink a lot, similar to online tools. Optional <span class="font-semibold text-gray-800">qpdf</span> (11+) can optimize further. Text-only or already-optimized files may not get much smaller. Without Ghostscript, the fallback only repacks pages and rarely reduces size.</p>
</div>

@if ($documents->isEmpty())
    <div class="bg-white rounded-xl shadow p-12 text-center">
        <p class="text-gray-500">No PDFs to compress yet. <a href="{{ route('pdf.index') }}" class="text-blue-600 hover:underline">Upload one</a>.</p>
    </div>
@else
    <form method="POST" action="{{ route('pdf.compress.store') }}" class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf

        @error('compress')
            <div class="p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg text-sm" role="alert">{{ $message }}</div>
        @enderror

        <div>
            <label for="document_id" class="block text-sm font-medium text-gray-700 mb-1">Document</label>
            <select id="document_id" name="document_id" required
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Choose a PDF&hellip;</option>
                @foreach ($documents as $doc)
                    <option value="{{ $doc->id }}">{{ $doc->original_name }} ({{ $doc->human_file_size }})</option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Compression level</label>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                @foreach ($levels as $level)
                    <label class="flex flex-col p-3 border rounded-lg cursor-pointer hover:bg-blue-50 transition has-[:checked]:border-blue-500 has-[:checked]:bg-blue-50">
                        <span class="font-semibold text-gray-800 capitalize">{{ $level }}</span>
                        <span class="text-xs text-gray-500 mt-1">
                            @switch($level)
                                @case('low') Highest quality (print), little size change @break
                                @case('medium') Stronger shrink, still print-usable @break
                                @case('recommended') Smaller images (~96&nbsp;dpi), good default @break
                                @case('maximum') Smallest (~72&nbsp;dpi images), try for big scans @break
                            @endswitch
                        </span>
                        <input type="radio" name="level" value="{{ $level }}"
                               class="sr-only" {{ $level === $default ? 'checked' : '' }}>
                    </label>
                @endforeach
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="px-5 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">Compress</button>
        </div>
    </form>
@endif
@endsection
