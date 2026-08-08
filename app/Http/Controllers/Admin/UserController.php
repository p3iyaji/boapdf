<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();

        $users = User::query()
            ->withCount('documents')
            ->when($status === 'trashed', fn ($query) => $query->onlyTrashed())
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($status === 'admin', fn ($query) => $query->where('is_admin', true))
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term);
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.users.index', [
            'users' => $users,
            'status' => $status,
            'search' => $request->string('q')->toString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.users.create');
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'is_admin' => $request->boolean('is_admin'),
            'is_active' => $request->boolean('is_active', true),
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User created.');
    }

    public function show(User $user): View
    {
        $user->loadCount('documents');
        $recentDocuments = $user->documents()->latest()->limit(10)->get();

        return view('admin.users.show', [
            'user' => $user,
            'recentDocuments' => $recentDocuments,
        ]);
    }

    public function edit(User $user): View
    {
        return view('admin.users.edit', ['user' => $user]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();

        if ($user->isAdmin() && ! $request->boolean('is_admin') && $this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages([
                'is_admin' => 'Cannot remove admin from the last active administrator.',
            ]);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'is_admin' => $request->boolean('is_admin'),
            'is_active' => $request->boolean('is_active'),
        ]);

        if (! empty($data['password'])) {
            $user->password = $data['password'];
        }

        $user->save();

        return redirect()
            ->route('admin.users.show', $user)
            ->with('success', 'User updated.');
    }

    public function destroy(Request $request, User $user, AuditLogger $auditLogger): RedirectResponse
    {
        if ($user->is($request->user())) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account while signed in.',
            ]);
        }

        if ($user->isAdmin() && $this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages([
                'user' => 'Cannot delete the last active administrator.',
            ]);
        }

        $auditLogger->log(
            action: 'admin.user.deleted',
            description: 'Administrator soft-deleted a user account.',
            subject: $user,
            metadata: [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'was_admin' => $user->isAdmin(),
            ],
        );

        $user->delete();

        return redirect()
            ->route('admin.users.index')
            ->with('success', 'User soft-deleted.');
    }

    public function restore(int $user): RedirectResponse
    {
        $trashed = User::onlyTrashed()->findOrFail($user);
        $trashed->restore();

        return redirect()
            ->route('admin.users.show', $trashed)
            ->with('success', 'User restored.');
    }

    public function updateActivation(Request $request, User $user): RedirectResponse
    {
        $activate = $request->boolean('activate');

        if (! $activate) {
            if ($user->is($request->user())) {
                throw ValidationException::withMessages([
                    'user' => 'You cannot deactivate your own account.',
                ]);
            }

            if ($user->isAdmin() && $this->isLastActiveAdmin($user)) {
                throw ValidationException::withMessages([
                    'user' => 'Cannot deactivate the last active administrator.',
                ]);
            }

            $user->deactivate();

            return back()->with('success', 'User deactivated.');
        }

        $user->activate();

        return back()->with('success', 'User activated.');
    }

    private function isLastActiveAdmin(User $user): bool
    {
        return ! User::query()
            ->where('is_admin', true)
            ->where('is_active', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }
}
