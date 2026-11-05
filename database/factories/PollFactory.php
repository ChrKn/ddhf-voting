<?php

namespace Database\Factories;

use App\Enums\PollMode;
use App\Enums\PollStatus;
use App\Enums\PollType;
use App\Models\Assembly;
use App\Models\Poll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Standard: Antrag mit Ja / Nein / Enthaltung.
 *
 * @extends Factory<Poll>
 */
class PollFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assembly_id' => Assembly::factory(),
            'title' => 'Antrag: '.fake()->sentence(4),
            'type' => PollType::Motion,
            'mode' => PollMode::Secret,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Poll $poll) {
            if ($poll->type === PollType::Motion && ! $poll->positions()->exists()) {
                $position = $poll->positions()->create(['title' => $poll->title]);
                $position->options()->createMany([
                    ['label' => 'Dafür', 'sort' => 1],
                    ['label' => 'Dagegen', 'sort' => 2],
                    ['label' => 'Enthaltung', 'is_abstention' => true, 'sort' => 3],
                ]);
            }
        });
    }

    public function public(): static
    {
        return $this->state(fn (array $attributes) => ['mode' => PollMode::Public]);
    }

    public function status(PollStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }

    /**
     * Wahl mit Ämtern und Kandidat:innen.
     *
     * @param  array<string, list<string>>  $positions  Amt => Kandidat:innen
     */
    public function election(array $positions): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PollType::Election,
            'title' => 'Wahl',
        ])->afterCreating(function (Poll $poll) use ($positions) {
            $sort = 0;
            foreach ($positions as $title => $candidates) {
                $position = $poll->positions()->create(['title' => $title, 'sort' => ++$sort]);
                foreach ($candidates as $i => $candidate) {
                    $position->options()->create(['label' => $candidate, 'sort' => $i + 1]);
                }
                $position->options()->create(['label' => 'Enthaltung', 'is_abstention' => true, 'sort' => 999]);
            }
        });
    }
}
