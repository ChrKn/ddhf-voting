<?php

namespace App\Services;

use App\Enums\AssemblyEventType;
use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Models\Assembly;
use App\Models\AssemblyVotingMember;
use App\Models\VotingMember;
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
     * Ein erneuter Check-in ändert den Snapshot nicht, auch nicht nach einem
     * Checkout: Dann gilt die Stimmenzahl vom ersten Check-in dieser
     * Versammlung, selbst wenn sich die Mitgliederzahl zwischendurch geändert hat.
     */
    public function checkIn(Assembly $assembly, VotingMember $votingMember): void
    {
        if ($assembly->status === AssemblyStatus::Closed) {
            throw new VotingException('Die Versammlung ist beendet.');
        }

        if (! $votingMember->active) {
            throw new VotingException("{$votingMember->name} ist inaktiv und kann nicht eingecheckt werden.");
        }

        if ($assembly->hasCheckedIn($votingMember)) {
            return;
        }

        $firstCheckIn = $assembly->events()
            ->where('type', AssemblyEventType::CheckedIn)
            ->where('voting_member_id', $votingMember->getKey())
            ->value('votes');

        $votes = $firstCheckIn === null ? $votingMember->votes : (int) $firstCheckIn;

        DB::transaction(function () use ($assembly, $votingMember, $votes) {
            $assembly->votingMembers()->attach($votingMember, [
                'votes' => $votes,
                'checked_in_at' => now(),
            ]);
            $assembly->record(AssemblyEventType::CheckedIn, votingMember: $votingMember, votes: $votes);
        });
    }

    public function checkOut(Assembly $assembly, VotingMember $votingMember): void
    {
        if ($assembly->status === AssemblyStatus::Closed) {
            throw new VotingException('Die Versammlung ist beendet.');
        }

        $votes = $this->votesOf($assembly, $votingMember);

        if ($votes === null) {
            return;
        }

        DB::transaction(function () use ($assembly, $votingMember, $votes) {
            $assembly->votingMembers()->detach($votingMember);
            $assembly->record(AssemblyEventType::CheckedOut, votingMember: $votingMember, votes: $votes);
        });
    }

    /** Stimmen-Snapshot eines eingecheckten Vereins (null = nicht eingecheckt). */
    public function votesOf(Assembly $assembly, VotingMember $votingMember): ?int
    {
        $votes = AssemblyVotingMember::query()
            ->where('assembly_id', $assembly->getKey())
            ->where('voting_member_id', $votingMember->getKey())
            ->value('votes');

        return $votes === null ? null : (int) $votes;
    }
}
