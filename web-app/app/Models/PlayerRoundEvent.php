<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerRoundEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'player_steam_id',
        'round_number',

        // Gun Fight fields
        'kills',
        'assists',
        'died',
        'damage',
        'headshots',
        'first_kill',
        'first_death',
        'round_time_of_death',
        'kills_with_awp',

        // Grenade fields
        'damage_dealt',
        'flashes_thrown',
        'friendly_flash_duration',
        'enemy_flash_duration',
        'friendly_players_affected',
        'enemy_players_affected',
        'flashes_leading_to_kill',
        'flashes_leading_to_death',
        'grenade_effectiveness',

        // Trade fields
        'successful_trades',
        'total_possible_trades',
        'successful_traded_deaths',
        'total_possible_traded_deaths',

        // Clutch fields
        'clutch_attempts_1v1',
        'clutch_attempts_1v2',
        'clutch_attempts_1v3',
        'clutch_attempts_1v4',
        'clutch_attempts_1v5',
        'clutch_wins_1v1',
        'clutch_wins_1v2',
        'clutch_wins_1v3',
        'clutch_wins_1v4',
        'clutch_wins_1v5',

        'time_to_contact',

        // Economy fields
        'is_eco',
        'is_force_buy',
        'is_full_buy',
        'kills_vs_eco',
        'kills_vs_force_buy',
        'kills_vs_full_buy',
        'grenade_value_lost_on_death',
    ];

    protected $casts = [
        'died' => 'boolean',
        'first_kill' => 'boolean',
        'first_death' => 'boolean',
        'friendly_flash_duration' => 'decimal:3',
        'enemy_flash_duration' => 'decimal:3',
        'grenade_effectiveness' => 'decimal:4',
        'time_to_contact' => 'decimal:3',
        'is_eco' => 'boolean',
        'is_force_buy' => 'boolean',
        'is_full_buy' => 'boolean',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
