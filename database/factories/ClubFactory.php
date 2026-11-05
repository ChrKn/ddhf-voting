<?php

namespace Database\Factories;

use App\Models\Club;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Club>
 */
class ClubFactory extends Factory
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
}
