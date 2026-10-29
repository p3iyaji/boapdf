<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ConversionLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ConversionLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = ConversionLog::query()
            ->with('document.user')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('source_format', 'like', $term)
                        ->orWhere('target_format', 'like', $term)
                        ->orWhere('error_message', 'like', $term);
                });
            })
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('admin.conversion-logs.index', [
            'logs' => $logs,
            'search' => $request->string('q')->toString(),
            'status' => $request->string('status')->toString(),
        ]);
    }
}
