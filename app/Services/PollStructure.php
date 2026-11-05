<?php

namespace App\Services;

use App\Enums\PollType;
use App\Models\Poll;
use App\Models\PollPosition;
use Illuminate\Support\Facades\DB;

/**
 * Hält die Struktur einer Abstimmung konsistent:
 * - Antrag: genau eine Position mit Dafür / Dagegen / Enthaltung
 * - Wahl: jede Position hat genau eine Enthaltungs-Option
 */
class PollStructure
{
    public const MOTION_OPTIONS = ['Dafür', 'Dagegen'];

    public const ABSTENTION = 'Enthaltung';

    public function sync(Poll $poll): void
    {
        DB::transaction(function () use ($poll) {
            $poll->type === PollType::Motion
                ? $this->syncMotion($poll)
                : $this->syncElection($poll);
        });

        $poll->unsetRelation('positions');
    }

    private function syncMotion(Poll $poll): void
    {
        $positions = $poll->positions()->with('options')->get();
        $position = $positions->first();

        $isStandard = $positions->count() === 1
            && $position?->options->pluck('label')->all() === [...self::MOTION_OPTIONS, self::ABSTENTION];

        if (! $isStandard) {
            $poll->positions()->delete();
            $position = $poll->positions()->create(['title' => $poll->title]);

            foreach (self::MOTION_OPTIONS as $i => $label) {
                $position->options()->create(['label' => $label, 'sort' => $i + 1]);
            }
            $this->ensureAbstention($position);

            return;
        }

        /** @var PollPosition $position */
        $position->update(['title' => $poll->title]);
    }

    private function syncElection(Poll $poll): void
    {
        foreach ($poll->positions()->get() as $position) {
            $this->ensureAbstention($position);
        }
    }

    private function ensureAbstention(PollPosition $position): void
    {
        $abstentions = $position->options()->where('is_abstention', true)->orderBy('id')->get();

        if ($abstentions->isEmpty()) {
            $position->options()->create(['label' => self::ABSTENTION, 'is_abstention' => true, 'sort' => 999]);

            return;
        }

        $abstentions->skip(1)->each->delete();
    }
}
