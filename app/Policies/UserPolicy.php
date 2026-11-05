<?php

namespace App\Policies;

use App\Models\User;

/** Personen und Rollen verwaltet nur der Systemadmin. */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function view(User $user, User $model): bool
    {
        return $user->isSystemAdmin() || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, User $model): bool
    {
        // Sich selbst zu löschen würde den letzten Admin aussperren
        return $user->isSystemAdmin() && ! $user->is($model);
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }
}
