<?php

namespace App\Services;

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

        return [
            'clubs_present' => $this->poll->assembly->clubs()->count(),
            'clubs_voted' => (clone $participations)->count(),
            'votes_present' => $this->poll->assembly->totalVotes(),
            'votes_cast' => (int) (clone $participations)->sum('votes'),
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
