<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        $logs = AuditLog::query()
            ->with(['user' => fn ($query) => $query->withTrashed()])
            ->when($request->filled('action'), fn ($query) => $query->where('action', $request->string('action')->toString()))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('action', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhere('actor_name', 'like', $term)
                        ->orWhere('actor_email', 'like', $term)
                        ->orWhere('ip_address', 'like', $term);
                });
            })
            ->latest()
            ->paginate(30)
            ->withQueryString();

        $actions = AuditLog::query()
            ->select('action')
            ->distinct()
            ->orderBy('action')
            ->pluck('action');

        return view('admin.audit-logs.index', [
            'logs' => $logs,
            'actions' => $actions,
            'search' => $request->string('q')->toString(),
            'action' => $request->string('action')->toString(),
        ]);
    }
}
