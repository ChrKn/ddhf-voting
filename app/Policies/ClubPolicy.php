<?php

namespace App\Policies;

use App\Models\Club;
use App\Models\User;

/**
 * Vereine pflegt nur der Systemadmin. Verwalter:innen dürfen sie sehen
 * (für Check-in und Versammlungsleitung), aber nicht ändern – insbesondere
 * nicht die Mitgliederzahl, von der die Stimmen abhängen.
 */
class ClubPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage();
    }

    public function view(User $user, Club $club): bool
    {
        return $user->canManage() || $user->represents($club);
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function update(User $user, Club $club): bool
    {
        return $user->isSystemAdmin();
    }

    public function updateMemberCount(User $user, Club $club): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, Club $club): bool
    {
        return $user->isSystemAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }
}
