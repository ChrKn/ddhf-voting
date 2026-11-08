<?php

namespace Database\Factories;

use App\Enums\VotingMemberType;
use App\Models\VotingMember;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VotingMember>
 */
class VotingMemberFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->city().' '.fake()->randomElement(['Fechtschule', 'HEMA e.V.', 'Schwertkampf', 'Fechtgilde']),
            'member_count' => fake()->numberBetween(5, 220),
            'active' => true,
        ];
    }

    public function members(int $count): static
    {
        return $this->state(fn (array $attributes) => ['member_count' => $count]);
    }

    /** Ein Präsidiumsposten mit fester Stimmenzahl statt Mitgliederzahl. */
    public function boardMember(int $votes = 1, ?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name ?? fake()->unique()->randomElement(['Präsident:in', 'Vizepräsident:in', 'Schatzmeister:in', 'Schriftführer:in', 'Sportwart:in']),
            'type' => VotingMemberType::BoardMember,
            'member_count' => null,
            'votes' => $votes,
        ]);
    }
}
