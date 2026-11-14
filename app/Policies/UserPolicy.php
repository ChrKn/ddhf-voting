<?php

namespace App\Policies;

use App\Enums\Role;
use App\Models\User;

/**
 * Personen und Rollen verwaltet der Systemadmin. Verwalter:innen sehen die
 * Liste, können Vertreter:innen anlegen, bearbeiten (z. B. mit Stimmberechtigten
 * verknüpfen), einladen oder ihnen ein vorläufiges Passwort geben, z.B. wenn bei
 * der Versammlung jemand nicht hereinkommt. Rollen vergeben sie nicht: Was sie
 * anlegen, ist immer Vertreter:in.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage();
    }

    public function view(User $user, User $model): bool
    {
        return $user->canManage() || $user->is($model);
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, User $model): bool
    {
        return $user->isSystemAdmin() || ($user->isManager() && $model->role === Role::Member);
    }

    /** Rollen vergibt nur der Systemadmin, und die eigene Rolle niemand (sonst sperrt man sich aus) */
    public function changeRole(User $user, ?User $model = null): bool
    {
        return $user->isSystemAdmin() && ! ($model?->is($user) ?? false);
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

    public function invite(User $user, User $model): bool
    {
        return $user->isSystemAdmin() || ($user->isManager() && $model->role === Role::Member);
    }

    /** Nur bei Vertreter:innen, nicht bei Admins oder Verwalter:innen */
    public function setPassword(User $user, User $model): bool
    {
        return $user->canManage() && $model->role === Role::Member;
    }
}
