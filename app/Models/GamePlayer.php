<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GamePlayer extends Model
{
    protected $table = 'game_players';

    protected $fillable = [
        'game_id', 'name', 'is_host', 'is_alive',
        'is_ai', 'role_id', 'order_index',
    ];

    protected function casts(): array
    {
        return [
            'is_host' => 'boolean',
            'is_alive' => 'boolean',
            'is_ai' => 'boolean',
            'order_index' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(GameAction::class, 'player_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class, 'voter_id');
    }
}
