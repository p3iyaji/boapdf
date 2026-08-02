<?php

return [

    /*
    |--------------------------------------------------------------------------
    | File limits & cleanup
    |--------------------------------------------------------------------------
    |
    | Laravel file validation "max" is in kilobytes (51200 ≈ 50 MB).
    */
    'max_file_size' => (int) env('PDF_MAX_FILE_SIZE', 51200),

    'temp_cleanup_hours' => (int) env('PDF_TEMP_CLEANUP_HOURS', 24),

    /*
    | Library documents (all operation types) older than this many days are
    | removed automatically for the owning user, including the file on disk.
    | Set to 0 to disable pruning (scheduled command still runs but skips work).
    */
    'upload_retention_days' => (int) env('PDF_UPLOAD_RETENTION_DAYS', 30),

    'temp_directories' => [
        'uploads',
        'merged',
        'compressed',
        'signed',
        'converted',
        'temp',
    ],

    'allowed_mime_types' => [
        'application/pdf',
    ],

    /*
    |--------------------------------------------------------------------------
    | Compression defaults
    |--------------------------------------------------------------------------
    */

    'default_compression' => env('PDF_DEFAULT_COMPRESSION', 'recommended'),

    'compression_levels' => ['low', 'medium', 'recommended', 'maximum'],

    'ghostscript_path' => env('GHOSTSCRIPT_PATH'),

    /*
    | Optional second pass after Ghostscript: recompresses images inside the PDF
    | when built with optimize-images support (qpdf 11+). Detected on PATH if unset.
    */
    'qpdf_path' => env('QPDF_PATH'),

    'libreoffice_path' => env('LIBREOFFICE_PATH'),

    /*
    |--------------------------------------------------------------------------
    | High-fidelity conversion pipeline
    |--------------------------------------------------------------------------
    |
    | Editable conversions use OCRmyPDF, pdf2docx and LibreOffice. Raster
    | conversions use Poppler's pdftocairo at print-quality resolution.
    */
    'conversion' => [
        'pdfinfo_path' => env('PDFINFO_PATH'),
        'pdftocairo_path' => env('PDFTOCAIRO_PATH'),
        'pdftotext_path' => env('PDFTOTEXT_PATH'),
        'qpdf_path' => env('QPDF_CONVERSION_PATH', env('QPDF_PATH')),
        'ocrmypdf_path' => env('OCRMYPDF_PATH'),
        'pdf2docx_path' => env('PDF2DOCX_PATH'),
        'libreoffice_path' => env('LIBREOFFICE_PATH'),
        'image_dpi' => (int) env('PDF_CONVERSION_IMAGE_DPI', 300),
        'jpeg_quality' => (int) env('PDF_CONVERSION_JPEG_QUALITY', 95),
        'ocr_enabled' => (bool) env('PDF_CONVERSION_OCR_ENABLED', true),
        'ocr_language' => env('PDF_CONVERSION_OCR_LANGUAGE', 'eng'),
        'ocr_jobs' => (int) env('PDF_CONVERSION_OCR_JOBS', 2),
        'docx_jobs' => (int) env('PDF_CONVERSION_DOCX_JOBS', 2),
        'process_timeout' => (int) env('PDF_CONVERSION_PROCESS_TIMEOUT', 120),
        'document_timeout' => (int) env('PDF_CONVERSION_DOCUMENT_TIMEOUT', 900),
        'ocr_timeout' => (int) env('PDF_CONVERSION_OCR_TIMEOUT', 1800),
    ],

];
