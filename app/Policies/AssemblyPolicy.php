<?php

namespace App\Policies;

use App\Enums\AssemblyStatus;
use App\Models\Assembly;
use App\Models\User;

/** Versammlungen und ihren Ablauf steuern Systemadmin und Verwalter:innen. */
class AssemblyPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage();
    }

    public function view(User $user, Assembly $assembly): bool
    {
        return $user->canManage();
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    public function update(User $user, Assembly $assembly): bool
    {
        return $user->canManage();
    }

    /** Check-in, Abstimmungen öffnen/schließen, Ergebnisse freigeben */
    public function conduct(User $user, Assembly $assembly): bool
    {
        return $user->canManage() && $assembly->status !== AssemblyStatus::Closed;
    }

    public function delete(User $user, Assembly $assembly): bool
    {
        // Durchgeführte Versammlungen bleiben als Protokoll erhalten
        return $user->canManage() && $assembly->status === AssemblyStatus::Draft;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
