<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.change-password');
    }

    public function editAdmin(): View
    {
        return view('admin.password.edit');
    }

    public function update(UpdatePasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        return back()->with('success', 'Your password has been updated.');
    }
}
