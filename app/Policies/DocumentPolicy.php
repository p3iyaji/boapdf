<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() && $user->isActive()) {
            return true;
        }

        return null;
    }

    public function view(User $user, Document $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function update(User $user, Document $document): bool
    {
        return $document->user_id === $user->id;
    }

    public function delete(User $user, Document $document): bool
    {
        return $document->user_id === $user->id;
    }
}
