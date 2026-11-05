<?php

namespace App\Services;

use App\Enums\PollStatus;
use App\Exceptions\VotingException;
use App\Models\Poll;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Statusmaschine einer Abstimmung: draft → open → closed → published.
 * Zurücksetzen (open/closed → draft) verwirft alle abgegebenen Stimmen.
 */
class PollLifecycle
{
    public function __construct(private readonly PollStructure $structure) {}

    public function open(Poll $poll): void
    {
        DB::transaction(function () use ($poll) {
            $poll = $this->locked($poll);

            if ($poll->status !== PollStatus::Draft) {
                throw new VotingException('Nur Abstimmungen in Vorbereitung können geöffnet werden.');
            }

            if (! $poll->assembly->isOpen()) {
                throw new VotingException('Die Versammlung muss zuerst eröffnet werden.');
            }

            $this->structure->sync($poll);
            $positions = $poll->positions()->with('options')->get();

            if ($positions->isEmpty()) {
                throw new VotingException('Die Abstimmung hat noch keine Ämter.');
            }

            foreach ($positions as $position) {
                if ($position->options->where('is_abstention', false)->isEmpty()) {
                    throw new VotingException("Für „{$position->title}“ gibt es noch keine Kandidat:innen.");
                }
            }

            // Zähler vorab anlegen, damit die Stimmabgabe nur noch hochzählt
            // und keine Zeilen in Abgabe-Reihenfolge entstehen.
            foreach ($positions as $position) {
                foreach ($position->options as $option) {
                    $poll->tallies()->create([
                        'poll_position_id' => $position->getKey(),
                        'poll_option_id' => $option->getKey(),
                        'votes' => 0,
                    ]);
                }
            }

            $poll->forceFill([
                'status' => PollStatus::Open,
                'opened_at' => now(),
                'closed_at' => null,
            ])->save();
        });

        $poll->refresh();
    }

    public function close(Poll $poll): void
    {
        $this->transition($poll, PollStatus::Open, PollStatus::Closed, 'Die Abstimmung ist nicht offen.', [
            'closed_at' => now(),
        ]);
    }

    public function publish(Poll $poll): void
    {
        $this->transition($poll, PollStatus::Closed, PollStatus::Published, 'Nur geschlossene Abstimmungen können veröffentlicht werden.');
    }

    /** Verwirft alle Stimmen und setzt die Abstimmung auf „In Vorbereitung“. */
    public function reset(Poll $poll, ?User $by = null): void
    {
        DB::transaction(function () use ($poll, $by) {
            $poll = $this->locked($poll);

            if (! in_array($poll->status, [PollStatus::Open, PollStatus::Closed], true)) {
                throw new VotingException('Nur offene oder geschlossene Abstimmungen können zurückgesetzt werden.');
            }

            $participations = $poll->participations()->count();
            $poll->participations()->delete();
            $poll->tallies()->delete();

            $poll->forceFill([
                'status' => PollStatus::Draft,
                'opened_at' => null,
                'closed_at' => null,
            ])->save();

            Log::notice('Abstimmung zurückgesetzt', [
                'poll_id' => $poll->getKey(),
                'title' => $poll->title,
                'discarded_participations' => $participations,
                'by_user_id' => $by?->getKey(),
            ]);
        });

        $poll->refresh();
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function transition(Poll $poll, PollStatus $from, PollStatus $to, string $error, array $extra = []): void
    {
        DB::transaction(function () use ($poll, $from, $to, $error, $extra) {
            $poll = $this->locked($poll);

            if ($poll->status !== $from) {
                throw new VotingException($error);
            }

            $poll->forceFill(['status' => $to, ...$extra])->save();
        });

        $poll->refresh();
    }

    /** Frisch aus der DB lesen (bei MySQL mit Zeilensperre, bei SQLite sperrt die Transaktion). */
    private function locked(Poll $poll): Poll
    {
        return Poll::query()->lockForUpdate()->findOrFail($poll->getKey());
    }
}
