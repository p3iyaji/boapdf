<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterUserRequest;
use App\Models\Document;
use App\Models\User;
use App\Rules\NotDisposableEmail;
use App\Services\AuditLogger;
use App\Support\DisposableEmail;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
            'password' => $data['password'],
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        $user->sendEmailVerificationNotification();

        app(AuditLogger::class)->log(
            action: 'auth.register',
            description: 'Registered a new account.',
            subject: $user,
            actor: $user,
            request: $request,
        );

        return redirect()->route('verification.notice');
    }

    public function authenticate(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', new NotDisposableEmail],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'The provided credentials do not match our records.'])
                ->onlyInput('email');
        }

        $user = Auth::user();

        if ($user === null || DisposableEmail::isDisposable($user->email)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Disposable email addresses are not allowed.'])
                ->onlyInput('email');
        }

        if (! $user->isActive()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Your account has been deactivated. Contact an administrator.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        app(AuditLogger::class)->log(
            action: 'auth.login',
            description: 'Signed in to the application.',
            subject: $user,
            actor: $user,
            request: $request,
        );

        return redirect()->intended(route('dashboard'));
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

    public function logout(Request $request, AuditLogger $auditLogger): RedirectResponse
    {
        $user = $request->user();

        if ($user !== null) {
            $auditLogger->log(
                action: 'auth.logout',
                description: 'Signed out of the application.',
                subject: $user,
                actor: $user,
                request: $request,
            );
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
