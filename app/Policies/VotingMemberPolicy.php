<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VotingMember;

/**
 * Stimmberechtigte pflegt nur der Systemadmin. Verwalter:innen dürfen sie sehen
 * (für Check-in und Versammlungsleitung), aber nicht ändern – insbesondere
 * nicht die Stimmen: Mitgliederzahl, feste Stimmen eines Präsidiumspostens, Typ.
 */
class VotingMemberPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage();
    }

    public function view(User $user, VotingMember $votingMember): bool
    {
        return $user->canManage() || $user->represents($votingMember);
    }

    public function create(User $user): bool
    {
        return $user->isSystemAdmin();
    }

    public function update(User $user, VotingMember $votingMember): bool
    {
        return $user->isSystemAdmin();
    }

    public function updateVotes(User $user, ?VotingMember $votingMember = null): bool
    {
        return $user->isSystemAdmin();
    }

    public function delete(User $user, VotingMember $votingMember): bool
    {
        return $user->isSystemAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSystemAdmin();
    }
}
