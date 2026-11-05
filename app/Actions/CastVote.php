<?php

namespace App\Actions;

use App\Exceptions\VotingException;
use App\Models\Club;
use App\Models\Poll;
use App\Models\PollParticipation;
use App\Models\PollTally;
use App\Models\User;
use App\Services\AssemblyLifecycle;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Gibt die Stimme eines Vereins ab – einmal pro Abstimmung, mit allen Stimmen
 * des Vereins (Snapshot beim Check-in).
 *
 * Geheim: Es wird nur festgehalten, DASS der Verein abgestimmt hat; die Wahl
 * selbst landet ausschließlich in den aufsummierten Zählern.
 */
class CastVote
{
    public function __construct(private readonly AssemblyLifecycle $assemblies) {}

    /**
     * @param  array<int|string, int|string>  $choices  position_id => option_id
     */
    public function handle(User $user, Club $club, Poll $poll, array $choices): PollParticipation
    {
        if (! $user->represents($club)) {
            throw new VotingException('Du bist nicht berechtigt, für diesen Verein abzustimmen.');
        }

        try {
            return DB::transaction(function () use ($user, $club, $poll, $choices) {
                // Status innerhalb der Transaktion prüfen, damit ein gleichzeitiges
                // Schließen nicht übergangen wird.
                $poll = Poll::query()->with(['assembly', 'positions.options'])->lockForUpdate()->findOrFail($poll->getKey());

                if (! $poll->isOpen() || ! $poll->assembly->isOpen()) {
                    throw new VotingException('Die Abstimmung ist nicht (mehr) offen.');
                }

                $votes = $this->assemblies->votesOf($poll->assembly, $club);
                if ($votes === null) {
                    throw new VotingException("{$club->name} ist nicht zur Versammlung eingecheckt.");
                }

                if ($poll->hasVoted($club)) {
                    throw new VotingException("{$club->name} hat bereits abgestimmt.");
                }

                $selected = $this->validatedChoices($poll, $choices);

                $participation = PollParticipation::create([
                    'poll_id' => $poll->getKey(),
                    'club_id' => $club->getKey(),
                    'user_id' => $user->getKey(),
                    'votes' => $votes,
                    'public_choices' => $poll->isPublic() ? $selected : null,
                ]);

                foreach ($selected as $optionId) {
                    $updated = PollTally::query()
                        ->where('poll_id', $poll->getKey())
                        ->where('poll_option_id', $optionId)
                        ->increment('votes', $votes);

                    if ($updated !== 1) {
                        throw new VotingException('Die Abstimmung ist nicht korrekt eröffnet worden.');
                    }
                }

                return $participation;
            });
        } catch (UniqueConstraintViolationException) {
            // Zwei Personen desselben Vereins gleichzeitig: die zweite Abgabe scheitert hier.
            throw new VotingException("{$club->name} hat bereits abgestimmt.");
        }
    }

    /**
     * Genau eine gültige Option je Position.
     *
     * @param  array<int|string, int|string>  $choices
     * @return array<int, int> position_id => option_id
     */
    private function validatedChoices(Poll $poll, array $choices): array
    {
        $selected = [];

        foreach ($poll->positions as $position) {
            $optionId = $choices[$position->getKey()] ?? null;

            if ($optionId === null || $optionId === '') {
                throw new VotingException("Bitte für „{$position->title}“ eine Auswahl treffen.");
            }

            $option = $position->options->firstWhere('id', (int) $optionId);
            if ($option === null) {
                throw new VotingException("Ungültige Auswahl für „{$position->title}“.");
            }

            $selected[(int) $position->getKey()] = (int) $option->getKey();
        }

        $unknown = array_diff(array_map('intval', array_keys($choices)), array_keys($selected));
        if ($unknown !== []) {
            throw new VotingException('Die Auswahl passt nicht zu dieser Abstimmung.');
        }

        return $selected;
    }
}
