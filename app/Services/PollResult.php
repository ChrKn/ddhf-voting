<?php

namespace App\Services;

use App\Models\AssemblyVotingMember;
use App\Models\Poll;
use App\Models\PollParticipation;
use App\Models\VotingMember;
use Illuminate\Support\Collection;

/**
 * Auswertung einer Abstimmung: Fortschritt, Ergebnis je Position und – nur bei
 * öffentlichen Abstimmungen – das Detailergebnis je Verein.
 */
class PollResult
{
    public function __construct(private readonly Poll $poll)
    {
        $this->poll->loadMissing(['assembly', 'positions.options']);
    }

    public static function for(Poll $poll): self
    {
        return new self($poll);
    }

    /**
     * @return array{voting_members_present: int, voting_members_voted: int, votes_present: int, votes_cast: int}
     */
    public function progress(): array
    {
        $participations = $this->poll->participations();

        // Nach dem Beenden zählt die festgehaltene Anwesenheit, vorher die aktuelle
        $attendance = $this->poll->voting_members_present !== null && $this->poll->votes_present !== null
            ? ['voting_members_present' => $this->poll->voting_members_present, 'votes_present' => $this->poll->votes_present]
            : $this->currentAttendance();

        return [
            'voting_members_present' => $attendance['voting_members_present'],
            'voting_members_voted' => (clone $participations)->count(),
            'votes_present' => $attendance['votes_present'],
            'votes_cast' => (int) (clone $participations)->sum('votes'),
        ];
    }

    /**
     * Aktuell eingecheckte Mitglieder und ihre Stimmen. Wer schon abgestimmt
     * und danach ausgecheckt hat, zählt mit den abgegebenen Stimmen weiter
     * als anwesend, sonst stünden mehr abgegebene als anwesende Stimmen da.
     *
     * @return array{voting_members_present: int, votes_present: int}
     */
    public function currentAttendance(): array
    {
        $present = AssemblyVotingMember::query()->where('assembly_id', $this->poll->assembly_id);
        $leftAfterVoting = $this->poll->participations()->whereNotIn('voting_member_id', (clone $present)->select('voting_member_id'));

        return [
            'voting_members_present' => (clone $present)->count() + (clone $leftAfterVoting)->count(),
            'votes_present' => (int) (clone $present)->sum('votes') + (int) (clone $leftAfterVoting)->sum('votes'),
        ];
    }

    /**
     * Eingecheckte Vereine, die noch nicht abgestimmt haben.
     *
     * @return Collection<int, VotingMember>
     */
    public function pendingVotingMembers(): Collection
    {
        return $this->poll->assembly->votingMembers()
            ->whereNotIn('voting_members.id', $this->poll->participations()->select('voting_member_id'))
            ->orderBy('name')
            ->get();
    }

    /**
     * Ergebnis je Position, Optionen absteigend nach Stimmen (Enthaltung zuletzt).
     *
     * @return list<array{
     *     position_id: int,
     *     title: string,
     *     total: int,
     *     valid: int,
     *     options: list<array{option_id: int, label: string, club_hint: ?string, is_abstention: bool, votes: int, share: float}>
     * }>
     */
    public function positions(): array
    {
        $tallies = $this->poll->tallies()->pluck('votes', 'poll_option_id');
        $result = [];

        foreach ($this->poll->positions as $position) {
            $options = $position->options->map(fn ($option) => [
                'option_id' => (int) $option->getKey(),
                'label' => $option->label,
                'club_hint' => $option->club_hint,
                'is_abstention' => $option->is_abstention,
                'votes' => (int) ($tallies[$option->getKey()] ?? 0),
            ]);

            $total = $options->sum('votes');
            // Anteil bezogen auf gültige Stimmen ohne Enthaltungen
            $valid = $options->where('is_abstention', false)->sum('votes');

            $result[] = [
                'position_id' => (int) $position->getKey(),
                'title' => $position->title,
                'total' => $total,
                'valid' => $valid,
                'options' => $options
                    ->map(fn (array $o) => [
                        ...$o,
                        'share' => (! $o['is_abstention'] && $valid > 0) ? round($o['votes'] / $valid * 100, 1) : 0.0,
                    ])
                    ->sortBy([['is_abstention', 'asc'], ['votes', 'desc']])
                    ->values()
                    ->all(),
            ];
        }

        return $result;
    }

    /**
     * Detailergebnis (nur öffentliche Abstimmungen): wer hat wie gestimmt.
     *
     * @return list<array{votingMember: string, votes: int, choices: array<string, string>}>
     */
    public function details(): array
    {
        if (! $this->poll->isPublic()) {
            return [];
        }

        $labels = $this->poll->positions->flatMap->options->pluck('label', 'id');
        $titles = $this->poll->positions->pluck('title', 'id');

        return $this->poll->participations()
            ->with('votingMember')
            ->get()
            ->sortBy(fn (PollParticipation $p) => $p->votingMember->name)
            ->map(fn (PollParticipation $p) => [
                'votingMember' => $p->votingMember->name,
                'votes' => $p->votes,
                'choices' => collect($p->public_choices ?? [])
                    ->mapWithKeys(fn ($optionId, $positionId) => [
                        (string) ($titles[(int) $positionId] ?? '?') => (string) ($labels[(int) $optionId] ?? '?'),
                    ])
                    ->all(),
            ])
            ->values()
            ->all();
    }
}
