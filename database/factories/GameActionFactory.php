<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GameAction;
use App\Models\GamePlayer;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameActionFactory extends Factory
{
    protected $model = GameAction::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'player_id' => GamePlayer::factory(),
            'type' => 'kill',
            'target_player_id' => null,
            'phase' => 'night',
            'metadata' => [],
        ];
    }

    public function kill(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'kill',
        ]);
    }

    public function save(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'save',
        ]);
    }

    public function inspect(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'inspect',
        ]);
    }

    public function skip(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'skip',
            'metadata' => ['skipped' => true],
        ]);
    }

    public function night(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => 'night',
        ]);
    }

    public function day(): static
    {
        return $this->state(fn (array $attributes) => [
            'phase' => 'day',
        ]);
    }

    public function withTarget(int $targetId): static
    {
        return $this->state(fn (array $attributes) => [
            'target_player_id' => $targetId,
        ]);
    }
}