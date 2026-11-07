<?php

namespace App\Services;

use App\Enums\AssemblyEventType;
use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Models\Assembly;
use App\Models\AssemblyClub;
use App\Models\Club;
use Illuminate\Support\Facades\DB;

/** Versammlung öffnen/schließen und Anwesenheit (Check-in) der Vereine. */
class AssemblyLifecycle
{
    public function open(Assembly $assembly): void
    {
        if ($assembly->status !== AssemblyStatus::Draft) {
            throw new VotingException('Nur Versammlungen in Vorbereitung können eröffnet werden.');
        }

        DB::transaction(function () use ($assembly) {
            $assembly->update(['status' => AssemblyStatus::Open]);
            $assembly->record(AssemblyEventType::AssemblyOpened);
        });
    }

    public function close(Assembly $assembly): void
    {
        if ($assembly->status !== AssemblyStatus::Open) {
            throw new VotingException('Die Versammlung läuft nicht.');
        }

        if ($assembly->polls()->where('status', PollStatus::Open)->exists()) {
            throw new VotingException('Es ist noch eine Abstimmung offen. Bitte zuerst schließen.');
        }

        DB::transaction(function () use ($assembly) {
            $assembly->update(['status' => AssemblyStatus::Closed]);
            $assembly->record(AssemblyEventType::AssemblyClosed);
        });
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

        $votes = $club->votes;

        DB::transaction(function () use ($assembly, $club, $votes) {
            $assembly->clubs()->attach($club, [
                'votes' => $votes,
                'checked_in_at' => now(),
            ]);
            $assembly->record(AssemblyEventType::CheckedIn, club: $club, votes: $votes);
        });
    }

    public function checkOut(Assembly $assembly, Club $club): void
    {
        if ($assembly->status === AssemblyStatus::Closed) {
            throw new VotingException('Die Versammlung ist beendet.');
        }

        $votes = $this->votesOf($assembly, $club);

        if ($votes === null) {
            return;
        }

        DB::transaction(function () use ($assembly, $club, $votes) {
            $assembly->clubs()->detach($club);
            $assembly->record(AssemblyEventType::CheckedOut, club: $club, votes: $votes);
        });
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
