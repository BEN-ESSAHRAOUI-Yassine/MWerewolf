<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameAction extends Model
{
    protected $table = 'game_actions';

    protected $fillable = [
        'game_id', 'player_id', 'type', 'target_player_id',
        'phase', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'player_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'target_player_id');
    }
}
