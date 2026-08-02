<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Models\Document;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        return view('auth.register');
    }

    public function register(RegisterUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        }

        return back()
            ->withErrors(['email' => 'The provided credentials do not match our records.'])
            ->onlyInput('email');
    }

    public function dashboard(): View
    {
        $userId = Auth::id();

        $stats = [
            'total' => Document::query()->where('user_id', $userId)->count(),
            'merged' => Document::query()->where('user_id', $userId)->where('operation_type', Document::OP_MERGED)->count(),
            'compressed' => Document::query()->where('user_id', $userId)->where('operation_type', Document::OP_COMPRESSED)->count(),
            'signed' => Document::query()->where('user_id', $userId)->where('operation_type', Document::OP_SIGNED)->count(),
        ];

        $recent = Document::query()->where('user_id', $userId)->latest()->limit(5)->get();

        return view('dashboard', [
            'stats' => $stats,
            'recent' => $recent,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
