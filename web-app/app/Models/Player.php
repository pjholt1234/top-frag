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
            ->withPivot(['team'])
            ->withTimestamps();
    }

    public function gunfightEventsAsPlayer1(): HasMany
    {
        return $this->hasMany(GunfightEvent::class, 'player_1_steam_id', 'steam_id');
    }

    public function gunfightEventsAsPlayer2(): HasMany
    {
        return $this->hasMany(GunfightEvent::class, 'player_2_steam_id', 'steam_id');
    }

    public function gunfightEventsAsVictor(): HasMany
    {
        return $this->hasMany(GunfightEvent::class, 'victor_steam_id', 'steam_id');
    }

    public function grenadeEvents(): HasMany
    {
        return $this->hasMany(GrenadeEvent::class, 'player_steam_id', 'steam_id');
    }

    public function damageEventsAsAttacker(): HasMany
    {
        return $this->hasMany(DamageEvent::class, 'attacker_steam_id', 'steam_id');
    }

    public function damageEventsAsVictim(): HasMany
    {
        return $this->hasMany(DamageEvent::class, 'victim_steam_id', 'steam_id');
    }

    public function playerRoundEvents(): HasMany
    {
        return $this->hasMany(PlayerRoundEvent::class, 'player_steam_id', 'steam_id');
    }

    public function playerMatchEvents(): HasMany
    {
        return $this->hasMany(PlayerMatchEvent::class, 'player_steam_id', 'steam_id');
    }

    public function playerRanks(): HasMany
    {
        return $this->hasMany(PlayerRank::class);
    }

    public function playerWonMatch(GameMatch $match): bool
    {
        $team = $match->players?->where('steam_id', $this->steam_id)->first()?->pivot->team;

        if (! $team) {
            return false;
        }

        return $match->winning_team === $team;
    }
}
