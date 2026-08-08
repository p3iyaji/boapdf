<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function log(
        string $action,
        ?string $description = null,
        ?Model $subject = null,
        array $metadata = [],
        ?User $actor = null,
        ?Request $request = null,
        ?string $actorName = null,
        ?string $actorEmail = null,
    ): AuditLog {
        $request ??= request();
        $actor ??= Auth::user();

        return AuditLog::query()->create([
            'user_id' => $actor?->id,
            'actor_name' => $actorName ?? $actor?->name,
            'actor_email' => $actorEmail ?? $actor?->email,
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }

    public function logFromRequest(Request $request): ?AuditLog
    {
        if (! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return null;
        }

        if ($request->session()->has('errors')) {
            return null;
        }

        $routeName = $request->route()?->getName();

        if ($routeName === null || $this->shouldSkipRoute($routeName)) {
            return null;
        }

        $actor = $request->user();

        if ($actor === null) {
            return null;
        }

        return $this->log(
            action: $routeName,
            description: $this->describeRoute($routeName, $request->method()),
            metadata: [
                'method' => $request->method(),
                'path' => '/'.$request->path(),
            ],
            actor: $actor,
            request: $request,
        );
    }

    private function shouldSkipRoute(string $routeName): bool
    {
        return in_array($routeName, [
            'logout',
            'authenticate',
            'register.store',
            'profile.update',
            'profile.password',
            'profile.destroy',
            'password.change',
            'admin.password.update',
            'admin.users.destroy',
            'verification.send',
        ], true);
    }

    private function describeRoute(string $routeName, string $method): string
    {
        $label = Str::of($routeName)->replace(['.', '-', '_'], ' ')->title()->toString();

        return strtoupper($method).': '.$label;
    }
}
