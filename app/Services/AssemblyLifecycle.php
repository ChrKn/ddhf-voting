<?php

namespace App\Services;

use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Models\Assembly;
use App\Models\AssemblyClub;
use App\Models\Club;

/** Versammlung öffnen/schließen und Anwesenheit (Check-in) der Vereine. */
class AssemblyLifecycle
{
    public function open(Assembly $assembly): void
    {
        if ($assembly->status !== AssemblyStatus::Draft) {
            throw new VotingException('Nur Versammlungen in Vorbereitung können eröffnet werden.');
        }

        $assembly->update(['status' => AssemblyStatus::Open]);
    }

    public function close(Assembly $assembly): void
    {
        if ($assembly->status !== AssemblyStatus::Open) {
            throw new VotingException('Die Versammlung läuft nicht.');
        }

        if ($assembly->polls()->where('status', PollStatus::Open)->exists()) {
            throw new VotingException('Es ist noch eine Abstimmung offen. Bitte zuerst schließen.');
        }

        $assembly->update(['status' => AssemblyStatus::Closed]);
    }

    /**
     * Checkt einen Verein ein und friert seine aktuelle Stimmenzahl ein.
     * Ein erneuter Check-in ändert den Snapshot nicht.
     */
    public function checkIn(Assembly $assembly, Club $club): void
    {
        if ($assembly->status === AssemblyStatus::Closed) {
            throw new VotingException('Die Versammlung ist beendet.');
        }

        if (! $club->active) {
            throw new VotingException("{$club->name} ist inaktiv und kann nicht eingecheckt werden.");
        }

        if ($assembly->hasCheckedIn($club)) {
            return;
        }

        $assembly->clubs()->attach($club, [
            'votes' => $club->votes,
            'checked_in_at' => now(),
        ]);
    }

    public function checkOut(Assembly $assembly, Club $club): void
    {
        if ($assembly->status === AssemblyStatus::Closed) {
            throw new VotingException('Die Versammlung ist beendet.');
        }

        $assembly->clubs()->detach($club);
    }

    /** Stimmen-Snapshot eines eingecheckten Vereins (null = nicht eingecheckt). */
    public function votesOf(Assembly $assembly, Club $club): ?int
    {
        $votes = AssemblyClub::query()
            ->where('assembly_id', $assembly->getKey())
            ->where('club_id', $club->getKey())
            ->value('votes');

        return $votes === null ? null : (int) $votes;
    }
}
