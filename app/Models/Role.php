<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    protected $fillable = [
        'name', 'slug', 'faction', 'description',
        'night_order', 'max_uses', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_uses' => 'array',
            'is_active' => 'boolean',
            'night_order' => 'integer',
        ];
    }

    public function actions(): HasMany
    {
        return $this->hasMany(RoleAction::class);
    }

    public function players()
    {
        return $this->hasMany(GamePlayer::class);
    }
}
