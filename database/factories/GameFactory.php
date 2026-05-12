<?php

namespace Database\Factories;

use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    protected $model = Game::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->bothify('??????')),
            'status' => 'waiting',
            'mode' => 'human_narrator',
            'current_phase' => null,
            'active_role' => null,
            'phase_ends_at' => null,
            'round' => 0,
        ];
    }

    public function playing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'playing',
            'current_phase' => 'night',
            'round' => 1,
        ]);
    }

    public function finished(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'finished',
            'current_phase' => null,
        ]);
    }

    public function autoNarrator(): static
    {
        return $this->state(fn (array $attributes) => [
            'mode' => 'auto_narrator',
        ]);
    }

    public function nightPhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_phase' => 'night',
        ]);
    }

    public function dayPhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_phase' => 'day',
        ]);
    }

    public function votingPhase(): static
    {
        return $this->state(fn (array $attributes) => [
            'current_phase' => 'voting',
        ]);
    }
}