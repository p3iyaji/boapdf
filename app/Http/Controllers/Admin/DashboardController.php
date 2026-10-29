<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ConversionLog;
use App\Models\Document;
use App\Models\SignatureRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'stats' => [
                'users' => User::query()->count(),
                'users_inactive' => User::query()->where('is_active', false)->count(),
                'users_trashed' => User::onlyTrashed()->count(),
                'documents' => Document::query()->count(),
                'documents_failed' => Document::query()->where('status', Document::STATUS_FAILED)->count(),
                'signature_pending' => SignatureRequest::query()->where('status', SignatureRequest::STATUS_PENDING)->count(),
                'signature_signed' => SignatureRequest::query()->where('status', SignatureRequest::STATUS_SIGNED)->count(),
                'conversion_logs' => ConversionLog::query()->count(),
                'conversion_failures' => ConversionLog::query()->where('status', '!=', 'success')->count(),
                'audit_logs' => AuditLog::query()->count(),
                'account_deletions' => AuditLog::query()->where('action', 'account.deleted')->count(),
            ],
        ]);
    }
}
