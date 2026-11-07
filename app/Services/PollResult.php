<?php

namespace App\Services;

use App\Models\AssemblyClub;
use App\Models\Club;
use App\Models\Poll;
use App\Models\PollParticipation;
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
     * @return array{clubs_present: int, clubs_voted: int, votes_present: int, votes_cast: int}
     */
    public function progress(): array
    {
        $participations = $this->poll->participations();

        // Nach dem Beenden zählt die festgehaltene Anwesenheit, vorher die aktuelle
        $attendance = $this->poll->clubs_present !== null && $this->poll->votes_present !== null
            ? ['clubs_present' => $this->poll->clubs_present, 'votes_present' => $this->poll->votes_present]
            : $this->currentAttendance();

        return [
            'clubs_present' => $attendance['clubs_present'],
            'clubs_voted' => (clone $participations)->count(),
            'votes_present' => $attendance['votes_present'],
            'votes_cast' => (int) (clone $participations)->sum('votes'),
        ];
    }

    /**
     * Aktuell eingecheckte Mitglieder und ihre Stimmen. Wer schon abgestimmt
     * und danach ausgecheckt hat, zählt mit den abgegebenen Stimmen weiter
     * als anwesend, sonst stünden mehr abgegebene als anwesende Stimmen da.
     *
     * @return array{clubs_present: int, votes_present: int}
     */
    public function currentAttendance(): array
    {
        $present = AssemblyClub::query()->where('assembly_id', $this->poll->assembly_id);
        $leftAfterVoting = $this->poll->participations()->whereNotIn('club_id', (clone $present)->select('club_id'));

        return [
            'clubs_present' => (clone $present)->count() + (clone $leftAfterVoting)->count(),
            'votes_present' => (int) (clone $present)->sum('votes') + (int) (clone $leftAfterVoting)->sum('votes'),
        ];
    }

    /**
     * Eingecheckte Vereine, die noch nicht abgestimmt haben.
     *
     * @return Collection<int, Club>
     */
    public function pendingClubs(): Collection
    {
        return $this->poll->assembly->clubs()
            ->whereNotIn('clubs.id', $this->poll->participations()->select('club_id'))
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
     * @return list<array{club: string, votes: int, choices: array<string, string>}>
     */
    public function details(): array
    {
        if (! $this->poll->isPublic()) {
            return [];
        }

        $labels = $this->poll->positions->flatMap->options->pluck('label', 'id');
        $titles = $this->poll->positions->pluck('title', 'id');

        return $this->poll->participations()
            ->with('club')
            ->get()
            ->sortBy(fn (PollParticipation $p) => $p->club->name)
            ->map(fn (PollParticipation $p) => [
                'club' => $p->club->name,
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
