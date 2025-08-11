<?php

namespace App\Models;

use App\Enums\MatchType;
use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GameMatch extends Model
{
    use HasFactory;

    protected $table = 'matches';

    protected $fillable = [
        'match_hash',
        'map',
        'winning_team',
        'winning_team_score',
        'losing_team_score',
        'match_type',
        'start_timestamp',
        'end_timestamp',
        'total_rounds',
        'total_fight_events',
        'total_grenade_events',
        'playback_ticks',
    ];

    protected $casts = [
        'match_type' => MatchType::class,
        'processing_status' => ProcessingStatus::class,
        'winning_team' => 'string',
        'start_timestamp' => 'datetime',
        'end_timestamp' => 'datetime',
        'total_rounds' => 'integer',
        'total_fight_events' => 'integer',
        'total_grenade_events' => 'integer',
    ];

    // Relationships
    public function matchPlayers(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function players()
    {
        return $this->belongsToMany(Player::class, 'match_players', 'match_id', 'player_id')
            ->withPivot(['team'])
            ->withTimestamps();
    }

    public function gunfightEvents(): HasMany
    {
        return $this->hasMany(GunfightEvent::class, 'match_id');
    }

    public function grenadeEvents(): HasMany
    {
        return $this->hasMany(GrenadeEvent::class, 'match_id');
    }

    public function matchSummary()
    {
        return $this->hasOne(MatchSummary::class, 'match_id');
    }

    public function playerMatchSummaries(): HasMany
    {
        return $this->hasMany(PlayerMatchSummary::class, 'match_id');
    }

    public function demoProcessingJobs(): HasMany
    {
        return $this->hasMany(DemoProcessingJob::class, 'match_id');
    }

    public function damageEvents(): HasMany
    {
        return $this->hasMany(DamageEvent::class, 'match_id');
    }
}
