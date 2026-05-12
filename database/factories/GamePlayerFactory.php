<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class GamePlayerFactory extends Factory
{
    protected $model = GamePlayer::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'name' => $this->faker->name(),
            'is_host' => false,
            'is_alive' => true,
            'is_ai' => false,
            'role_id' => null,
            'order_index' => 0,
        ];
    }

    public function host(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_host' => true,
        ]);
    }

    public function dead(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_alive' => false,
        ]);
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => $role->id,
        ]);
    }
}