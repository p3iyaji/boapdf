<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function edit(): RedirectResponse
    {
        return redirect()->route('profile.edit');
    }

    public function editAdmin(): View
    {
        return view('admin.password.edit');
    }

    public function update(UpdatePasswordRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->validated('password'),
        ]);

        $auditLogger->log(
            action: 'password.changed',
            description: $request->routeIs('admin.*')
                ? 'Changed admin account password.'
                : 'Changed account password.',
            subject: $user,
        );

        return back()->with('success', 'Your password has been updated.');
    }
}
