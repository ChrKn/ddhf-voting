<?php

namespace App\Policies;

use App\Enums\PollStatus;
use App\Models\Poll;
use App\Models\User;

class PollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->canManage();
    }

    public function view(User $user, Poll $poll): bool
    {
        return $user->canManage();
    }

    public function create(User $user): bool
    {
        return $user->canManage();
    }

    /** Inhalt (Frage, Ämter, Optionen) ist nur vor dem Öffnen änderbar. */
    public function update(User $user, Poll $poll): bool
    {
        return $user->canManage() && $poll->status === PollStatus::Draft;
    }

    public function delete(User $user, Poll $poll): bool
    {
        return $user->canManage() && $poll->status === PollStatus::Draft;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /** Ergebnis vor der Freigabe sehen (Versammlungsleitung). */
    public function viewResults(User $user, Poll $poll): bool
    {
        return $user->canManage() || $poll->status === PollStatus::Published;
    }
}
