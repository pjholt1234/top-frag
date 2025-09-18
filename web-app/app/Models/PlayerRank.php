<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerRank extends Model
{
    use HasFactory;

    protected $fillable = [
        'player_id',
        'rank_type',
        'map',
        'rank',
        'rank_value',
    ];

    protected $casts = [
        'rank_value' => 'integer',
    ];

    // Relationships
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    // Scopes
    public function scopeByRankType(Builder $query, string $rankType): Builder
    {
        return $query->where('rank_type', $rankType);
    }

    public function scopeForPlayer(Builder $query, int $playerId): Builder
    {
        return $query->where('player_id', $playerId);
    }

    public function scopeLatest(Builder $query): Builder
    {
        return $query->orderBy('created_at', 'desc');
    }

    public function scopeByRankTypeAndPlayer(Builder $query, string $rankType, int $playerId): Builder
    {
        return $query->where('rank_type', $rankType)->where('player_id', $playerId);
    }

    public function scopeByMap(Builder $query, string $map): Builder
    {
        return $query->where('map', $map);
    }

    public function scopeByRankTypeMapAndPlayer(Builder $query, string $rankType, ?string $map, int $playerId): Builder
    {
        $query = $query->where('rank_type', $rankType)->where('player_id', $playerId);

        if ($map !== null) {
            $query->where('map', $map);
        } else {
            $query->whereNull('map');
        }

        return $query;
    }
}
