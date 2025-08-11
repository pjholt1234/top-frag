<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DamageEvent extends Model
{
    use HasFactory;

    protected $table = 'damage_events';

    protected $fillable = [
        'match_id',
        'armor_damage',
        'attacker_steam_id',
        'damage',
        'headshot',
        'health_damage',
        'round_number',
        'round_time',
        'tick_timestamp',
        'victim_steam_id',
        'weapon',
    ];

    protected $casts = [
        'armor_damage' => 'integer',
        'damage' => 'integer',
        'headshot' => 'boolean',
        'health_damage' => 'integer',
        'round_number' => 'integer',
        'round_time' => 'integer',
        'tick_timestamp' => 'integer',
    ];

    // Relationships
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function victimPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'victim_steam_id', 'steam_id');
    }

    public function attackingPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'attacker_steam_id', 'steam_id');
    }
}
