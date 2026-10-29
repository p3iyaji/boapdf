<?php

namespace App\Services;

use App\Models\Document;
use App\Models\User;
use App\Support\DocumentsDisk;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function delete(User $user): void
    {
        if ($user->isAdmin() && $this->isLastActiveAdmin($user)) {
            throw ValidationException::withMessages([
                'password' => 'Cannot delete the last active administrator account.',
            ]);
        }

        $this->auditLogger->log(
            action: 'account.deleted',
            description: 'User permanently closed their account and requested erasure of personal data.',
            subject: $user,
            metadata: [
                'user_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'documents_deleted' => $user->documents()->count(),
                'was_admin' => $user->isAdmin(),
            ],
            actor: $user,
        );

        DB::transaction(function () use ($user): void {
            $user->documents()->each(function (Document $document): void {
                if (filled($document->file_path) && DocumentsDisk::disk()->exists($document->file_path)) {
                    DocumentsDisk::disk()->delete($document->file_path);
                }

                $document->delete();
            });

            $user->forceFill([
                'name' => 'Deleted User',
                'email' => 'deleted-'.$user->id.'@deleted.local',
                'password' => str()->random(64),
                'remember_token' => null,
                'is_active' => false,
                'email_verified_at' => null,
            ])->save();

            $user->delete();
        });
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
