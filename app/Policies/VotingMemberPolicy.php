<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VotingMember;

/**
 * Verwalter:innen dürfen Stimmberechtigte einzeln anlegen (samt Mitgliederzahl bzw.
 * fester Stimmen) und bestehende bearbeiten, z. B. um Personen zu verknüpfen. Die
 * Stimmen eines bestehenden Eintrags – Mitgliederzahl, feste Stimmen eines
 * Präsidiumspostens, Typ – ändert danach nur der Systemadmin, ebenso Import und Löschen.
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
        return $user->canManage();
    }

    public function update(User $user, VotingMember $votingMember): bool
    {
        return $user->canManage();
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
