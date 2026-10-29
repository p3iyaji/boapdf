<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms', [
            'effectiveDate' => config('legal.effective_date'),
            'contactEmail' => config('legal.contact_email'),
            'operatorName' => config('legal.operator_name'),
            'governingLaw' => config('legal.governing_law'),
            'retentionDays' => (int) config('pdf.upload_retention_days', 30),
        ]);
    }

    public function privacy(): View
    {
        return view('legal.privacy', [
            'effectiveDate' => config('legal.effective_date'),
            'contactEmail' => config('legal.contact_email'),
            'operatorName' => config('legal.operator_name'),
            'retentionDays' => (int) config('pdf.upload_retention_days', 30),
            'tempCleanupHours' => (int) config('pdf.temp_cleanup_hours', 24),
            'maxFileSizeKb' => (int) config('pdf.max_file_size', 51200),
        ]);
    }
}
