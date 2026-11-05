<?php

namespace Database\Factories;

use App\Enums\PollMode;
use App\Enums\PollStatus;
use App\Enums\PollType;
use App\Models\Assembly;
use App\Models\Poll;
use App\Services\PollStructure;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Standard: Antrag mit Dafür / Dagegen / Enthaltung.
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
        return $this->afterCreating(fn (Poll $poll) => app(PollStructure::class)->sync($poll));
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
     * Wahl mit Ämtern und Kandidat:innen (Enthaltung ergänzt PollStructure).
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
            }

            // configure() läuft vor diesem Callback; Enthaltungen jetzt ergänzen
            app(PollStructure::class)->sync($poll);
        });
    }
}
