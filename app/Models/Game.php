<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Game extends Model
{
    protected $fillable = [
        'code', 'status', 'mode', 'current_phase',
        'phase_ends_at', 'created_by', 'round',
        'active_role', 'called_at',
    ];

    protected function casts(): array
    {
        return [
            'phase_ends_at' => 'datetime',
            'called_at' => 'datetime',
            'round' => 'integer',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function players(): HasMany
    {
        return $this->hasMany(GamePlayer::class);
    }

    public function alivePlayers()
    {
        return $this->players()->where('is_alive', true);
    }

    public function actions(): HasMany
    {
        return $this->hasMany(GameAction::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(GameEvent::class);
    }

    public function snapshots(): HasMany
    {
        return $this->hasMany(GameStateSnapshot::class);
    }
}
