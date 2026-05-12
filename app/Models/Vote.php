<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Vote extends Model
{
    protected $fillable = [
        'game_id', 'voter_id', 'target_id', 'round',
    ];

    protected function casts(): array
    {
        return [
            'round' => 'integer',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'voter_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(GamePlayer::class, 'target_id');
    }
}
