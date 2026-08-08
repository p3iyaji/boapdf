<?php

namespace App\Http\Controllers;

use App\Http\Requests\DeleteAccountRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AccountDeletionService;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class ProfileController extends Controller
{
    public function edit(): View
    {
        return view('profile.edit', [
            'user' => Auth::user(),
        ]);
    }

    public function update(UpdateProfileRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $emailChanged = $user->email !== $data['email'];

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        $auditLogger->log(
            action: 'profile.updated',
            description: 'Updated profile name and email.',
            subject: $user,
            metadata: [
                'email_changed' => $emailChanged,
            ],
        );

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()
                ->route('verification.notice')
                ->with('success', 'Profile updated. Please verify your new email address.');
        }

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Profile updated.');
    }

    public function updatePassword(UpdatePasswordRequest $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        $user->update([
            'password' => $request->validated('password'),
        ]);

        $auditLogger->log(
            action: 'password.changed',
            description: 'Changed account password.',
            subject: $user,
        );

        return redirect()
            ->route('profile.edit')
            ->with('success', 'Your password has been updated.');
    }

    public function destroy(
        DeleteAccountRequest $request,
        AccountDeletionService $accountDeletion,
    ): RedirectResponse {
        $user = $request->user();

        $accountDeletion->delete($user);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('login')
            ->with('success', 'Your account and personal data have been deleted.');
    }
}
