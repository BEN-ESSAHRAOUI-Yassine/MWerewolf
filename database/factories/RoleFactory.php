<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'slug' => $this->faker->unique()->word(),
            'faction' => 'village',
            'description' => $this->faker->sentence(),
            'night_order' => null,
            'max_uses' => null,
            'is_active' => true,
        ];
    }

    public function werewolf(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Werewolf',
            'slug' => 'werewolf',
            'faction' => 'werewolf',
            'description' => 'A cunning predator hiding among the village.',
            'night_order' => 1,
        ]);
    }

    public function seer(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Seer',
            'slug' => 'seer',
            'faction' => 'village',
            'description' => 'A visionary with the gift of sight.',
            'night_order' => 2,
        ]);
    }

    public function witch(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Witch',
            'slug' => 'witch',
            'faction' => 'village',
            'description' => 'A keeper of ancient potions.',
            'night_order' => 3,
            'max_uses' => ['save' => 1, 'kill' => 1],
        ]);
    }

    public function villager(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Villager',
            'slug' => 'villager',
            'faction' => 'village',
            'description' => 'An ordinary villager with no special abilities.',
            'night_order' => null,
        ]);
    }
}