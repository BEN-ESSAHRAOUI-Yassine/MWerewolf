<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameStateSnapshot extends Model
{
    protected $table = 'game_state_snapshots';

    protected $fillable = [
        'game_id', 'state',
    ];

    protected function casts(): array
    {
        return [
            'state' => 'array',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}
