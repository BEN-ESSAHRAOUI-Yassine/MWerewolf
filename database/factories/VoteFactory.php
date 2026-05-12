<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\GamePlayer;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;

class VoteFactory extends Factory
{
    protected $model = Vote::class;

    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'voter_id' => GamePlayer::factory(),
            'target_id' => GamePlayer::factory(),
            'round' => 1,
        ];
    }

    public function round(int $round): static
    {
        return $this->state(fn (array $attributes) => [
            'round' => $round,
        ]);
    }

    public function abstain(): static
    {
        return $this->state(fn (array $attributes) => [
            'target_id' => $attributes['voter_id'],
        ]);
    }
}