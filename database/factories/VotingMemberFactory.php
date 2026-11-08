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

    /**
     * Ein Präsidiumsposten mit fester Stimmenzahl statt Mitgliederzahl. Die echten Posten legt
     * die Migration an; erfundene Namen hier, damit sie nicht mit ihnen kollidieren.
     */
    public function boardMember(int $votes = 1, ?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name ?? 'Testposten '.fake()->unique()->numberBetween(1, 9999),
            'type' => VotingMemberType::BoardMember,
            'member_count' => null,
            'votes' => $votes,
        ]);
    }
}
