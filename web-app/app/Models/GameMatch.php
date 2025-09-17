<?php

namespace App\Models;

use App\Enums\MatchType;
use App\Enums\ProcessingStatus;
use App\Services\MatchCacheManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
        'playback_ticks',
        'uploaded_by',
        'game_mode',
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

    public function demoProcessingJob(): HasOne
    {
        return $this->hasOne(DemoProcessingJob::class, 'match_id');
    }

    public function damageEvents(): HasMany
    {
        return $this->hasMany(DamageEvent::class, 'match_id');
    }

    public function playerRoundEvents(): HasMany
    {
        return $this->hasMany(PlayerRoundEvent::class, 'match_id');
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'uploaded_by', 'id');
    }

    public function playerMatchEvents(): HasMany
    {
        return $this->hasMany(PlayerMatchEvent::class, 'match_id');
    }

    public function playerWasParticipant(Player $player): bool
    {
        return $this->players->contains($player);
    }

    public function invalidateMatchCache(): void
    {
        MatchCacheManager::invalidateAll($this->id);
    }
}
