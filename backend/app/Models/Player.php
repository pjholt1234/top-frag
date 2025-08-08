<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Player extends Model
{
    use HasFactory;

    protected $fillable = [
        'steam_id',
        'name',
        'first_seen_at',
        'last_seen_at',
        'total_matches',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'total_matches' => 'integer',
    ];

    // Relationships
    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'player_id');
    }

    public function matches()
    {
        return $this->belongsToMany(GameMatch::class, 'match_players', 'player_id', 'match_id')
            ->withPivot(['team', 'side_start'])
            ->withTimestamps();
    }

    public function gunfightEventsAsPlayer1(): HasMany
    {
        return $this->hasMany(GunfightEvent::class, 'player_1_id');
    }

    public function gunfightEventsAsPlayer2(): HasMany
    {
        return $this->hasMany(GunfightEvent::class, 'player_2_id');
    }

    public function gunfightEventsAsVictor(): HasMany
    {
        return $this->hasMany(GunfightEvent::class, 'victor_id');
    }

    public function grenadeEvents(): HasMany
    {
        return $this->hasMany(GrenadeEvent::class, 'player_id');
    }

    public function playerMatchSummaries(): HasMany
    {
        return $this->hasMany(PlayerMatchSummary::class, 'player_id');
    }
}
