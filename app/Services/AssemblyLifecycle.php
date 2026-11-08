<?php

namespace App\Services;

use App\Enums\AssemblyEventType;
use App\Enums\AssemblyStatus;
use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Models\Assembly;
use App\Models\AssemblyEvent;
use App\Models\AssemblyVotingMember;
use App\Models\User;
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
     * Checkt Stimmberechtigte ein und friert ihre aktuelle Stimmenzahl ein, bei
     * Mitgliedern zusammen mit der Mitgliederzahl, aus der sie berechnet wurde.
     * Ein erneuter Check-in ändert den Snapshot nicht, auch nicht nach einem
     * Checkout: Dann gilt der Snapshot vom ersten Check-in dieser Versammlung,
     * selbst wenn sich die Mitgliederzahl zwischendurch geändert hat – es sei denn,
     * er wurde seitdem korrigiert (siehe correctVotes()), dann gilt die Korrektur.
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

        $snapshot = $this->previousSnapshot($assembly, $votingMember);

        if ($snapshot === null) {
            $votes = $votingMember->votes;
            $memberCount = $votingMember->isBoardMember() ? null : $votingMember->member_count;
        } else {
            $votes = (int) $snapshot->votes;
            $memberCount = $snapshot->member_count;
        }

        DB::transaction(function () use ($assembly, $votingMember, $votes, $memberCount) {
            $assembly->votingMembers()->attach($votingMember, [
                'votes' => $votes,
                'member_count' => $memberCount,
                'checked_in_at' => now(),
            ]);
            $assembly->record(AssemblyEventType::CheckedIn, votingMember: $votingMember, votes: $votes, memberCount: $memberCount);
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

    /**
     * Korrigiert während der Versammlung eine falsche Mitgliederzahl (bzw. die Stimmen eines
     * Präsidiumspostens): in den Stammdaten und, falls schon eingecheckt, im Snapshot dieser
     * Versammlung. Beendete Abstimmungen bleiben, wie sie gezählt wurden; das Protokoll
     * zeigt mit Begründung und Zeitpunkt, ab wann der neue Wert gilt.
     *
     * Nicht während einer offenen Abstimmung: Wer schon abgestimmt hat, ist mit dem alten
     * Wert gezählt, und Summe wie Beteiligung passten nicht mehr zusammen.
     *
     * Die Berechtigung (Systemadmin oder Versammlungsleitung, siehe AssemblyPolicy::conduct)
     * prüft der Aufrufer. Die Stammdaten werden deshalb hier am Schutz im Model vorbei
     * gespeichert, der sonst nur dem Systemadmin Änderungen erlaubt.
     *
     * @param  int  $value  neue Mitgliederzahl, bei Präsidiumsposten neue Stimmenzahl
     */
    public function correctVotes(Assembly $assembly, VotingMember $votingMember, int $value, string $reason, ?User $by = null): void
    {
        if ($assembly->status === AssemblyStatus::Closed) {
            throw new VotingException('Die Versammlung ist beendet.');
        }

        if ($assembly->polls()->where('status', PollStatus::Open)->exists()) {
            throw new VotingException('Während einer offenen Abstimmung kann nichts korrigiert werden. Bitte erst die Abstimmung beenden.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new VotingException('Bitte eine Begründung angeben.');
        }

        $board = $votingMember->isBoardMember();
        if ($value < ($board ? 1 : 0)) {
            throw new VotingException($board ? 'Ein Präsidiumsposten braucht mindestens eine Stimme.' : 'Die Mitgliederzahl kann nicht negativ sein.');
        }

        $attendance = $this->attendanceOf($assembly, $votingMember);
        $oldVotes = $attendance !== null ? $attendance->votes : $votingMember->votes;
        $oldMemberCount = $attendance !== null ? $attendance->member_count : ($board ? null : $votingMember->member_count);

        $votes = $board ? $value : app(VoteWeightCalculator::class)->votesFor($value);
        $memberCount = $board ? null : $value;

        DB::transaction(function () use ($assembly, $votingMember, $attendance, $votes, $memberCount, $oldVotes, $oldMemberCount, $reason, $by, $board) {
            $votingMember->forceFill($board ? ['votes' => $votes] : ['member_count' => $memberCount])->saveQuietly();

            if ($attendance !== null) {
                $assembly->votingMembers()->updateExistingPivot($votingMember->getKey(), [
                    'votes' => $votes,
                    'member_count' => $memberCount,
                ]);
            }

            $assembly->record(
                AssemblyEventType::VotesCorrected,
                votingMember: $votingMember,
                votes: $votes,
                memberCount: $memberCount,
                details: ['old_votes' => $oldVotes, 'old_member_count' => $oldMemberCount, 'reason' => $reason],
                by: $by,
            );
        });
    }

    /**
     * Snapshot aus dieser Versammlung, falls die Stimmberechtigten schon einmal eingecheckt
     * waren: der jüngste Check-in bzw. die jüngste Korrektur. Eine Korrektur vor dem ersten
     * Check-in zählt nicht eigens, sie steht dann schon in den Stammdaten.
     */
    private function previousSnapshot(Assembly $assembly, VotingMember $votingMember): ?AssemblyEvent
    {
        $events = $assembly->events()->where('voting_member_id', $votingMember->getKey());

        if (! (clone $events)->where('type', AssemblyEventType::CheckedIn)->exists()) {
            return null;
        }

        return $events
            ->whereIn('type', [AssemblyEventType::CheckedIn, AssemblyEventType::VotesCorrected])
            ->reorder('id', 'desc')
            ->first();
    }

    /** Stimmen-Snapshot eingecheckter Stimmberechtigter (null = nicht eingecheckt). */
    public function votesOf(Assembly $assembly, VotingMember $votingMember): ?int
    {
        return $this->attendanceOf($assembly, $votingMember)?->votes;
    }

    /** Der ganze Snapshot vom Check-in: Stimmen und Mitgliederzahl (null = nicht eingecheckt). */
    public function attendanceOf(Assembly $assembly, VotingMember $votingMember): ?AssemblyVotingMember
    {
        return AssemblyVotingMember::query()
            ->where('assembly_id', $assembly->getKey())
            ->where('voting_member_id', $votingMember->getKey())
            ->first();
    }
}
