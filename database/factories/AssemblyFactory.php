<?php

namespace Database\Factories;

use App\Enums\AssemblyStatus;
use App\Models\Assembly;
use App\Models\VotingMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assembly>
 */
class AssemblyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => 'Mitgliederversammlung '.fake()->year(),
            'date' => fake()->dateTimeBetween('now', '+3 months'),
            'status' => AssemblyStatus::Draft,
        ];
    }

    public function open(): static
    {
        return $this->state(fn (array $attributes) => ['status' => AssemblyStatus::Open]);
    }

    /**
     * Checkt die Vereine mit ihrer aktuellen Stimmenzahl ein.
     *
     * @param  iterable<VotingMember>  $votingMembers
     */
    public function withCheckedIn(iterable $votingMembers): static
    {
        return $this->afterCreating(function (Assembly $assembly) use ($votingMembers) {
            foreach ($votingMembers as $votingMember) {
                $assembly->votingMembers()->attach($votingMember, ['votes' => $votingMember->votes, 'checked_in_at' => now()]);
            }
        });
    }
}
