@extends('layouts.app')

@section('title', 'Convert PDF - '.config('app.name'))

@section('content')
<div class="mb-6">
    <h1 class="text-2xl md:text-3xl font-bold text-gray-800">Convert PDF</h1>
    <p class="text-gray-600 mt-1">
        Export a PDF to an editable Word or HTML document, or render every page as a
        print-quality image.
    </p>
</div>

@if ($documents->isEmpty())
    <div class="bg-white rounded-xl shadow p-12 text-center">
        <p class="text-gray-500">
            You don't have any PDFs yet.
            <a href="{{ route('pdf.index') }}" class="text-blue-600 hover:underline">Upload one first</a>.
        </p>
    </div>
@else
    <form method="POST" action="{{ route('pdf.convert.store') }}"
          x-data="{ target: '' }"
          class="bg-white rounded-xl shadow p-6 space-y-5">
        @csrf

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
            <label class="block text-sm font-medium text-gray-700 mb-2">Target format</label>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-7 gap-3">
                @foreach ($targets as $format)
                    <label class="flex flex-col items-center p-3 border rounded-lg cursor-pointer hover:bg-blue-50 transition has-checked:border-blue-500 has-checked:bg-blue-50">
                        <span class="font-semibold text-gray-800 uppercase">{{ $format }}</span>
                        <span class="text-xs text-gray-500 mt-1">
                            @switch($format)
                                @case('html') Web page @break
                                @case('docx') Word doc @break
                                @case('doc') Legacy Word @break
                                @case('jpg') JPEG image @break
                                @case('jpeg') JPEG image @break
                                @case('png') PNG image @break
                                @case('txt') Plain text @break
                            @endswitch
                        </span>
                        <input type="radio" name="target" value="{{ $format }}" x-model="target" class="sr-only" required>
                    </label>
                @endforeach
            </div>
            <p class="text-xs text-gray-500 mt-2"
               x-show="target === 'jpg' || target === 'jpeg' || target === 'png'"
               x-cloak>
                Pages are rendered at 300 DPI. Multi-page PDFs download as a ZIP containing one image per page.
            </p>
            <p class="text-xs text-gray-500 mt-2"
               x-show="target === 'docx' || target === 'doc' || target === 'html'"
               x-cloak>
                Scanned pages are OCR processed before editable document reconstruction.
            </p>
        </div>

        <div>
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">
                PDF password <span class="font-normal text-gray-500">(only if encrypted)</span>
            </label>
            <input id="password" name="password" type="password" autocomplete="off" maxlength="1024"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                   aria-describedby="password-help">
            <p id="password-help" class="text-xs text-gray-500 mt-1">
                The password is used only during this conversion and is never stored.
            </p>
        </div>

        <div class="flex justify-end">
            <button type="submit"
                    class="px-5 py-2 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700">Convert &amp; download</button>
        </div>
    </form>
@endif
@endsection
