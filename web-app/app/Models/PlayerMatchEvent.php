<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMatchEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'player_steam_id',
        'kills',
        'assists',
        'deaths',
        'damage',
        'adr',
        'headshots',
        'first_kills',
        'first_deaths',
        'average_round_time_of_death',
        'kills_with_awp',
        'damage_dealt',
        'flashes_thrown',
        'fire_grenades_thrown',
        'smokes_thrown',
        'hes_thrown',
        'decoys_thrown',
        'friendly_flash_duration',
        'enemy_flash_duration',
        'friendly_players_affected',
        'enemy_players_affected',
        'flashes_leading_to_kills',
        'flashes_leading_to_deaths',
        'average_grenade_effectiveness',
        'total_successful_trades',
        'total_possible_trades',
        'total_traded_deaths',
        'total_possible_traded_deaths',
        'clutch_wins_1v1',
        'clutch_wins_1v2',
        'clutch_wins_1v3',
        'clutch_wins_1v4',
        'clutch_wins_1v5',
        'clutch_attempts_1v1',
        'clutch_attempts_1v2',
        'clutch_attempts_1v3',
        'clutch_attempts_1v4',
        'clutch_attempts_1v5',
        'average_time_to_contact',
        'kills_vs_eco',
        'kills_vs_force_buy',
        'kills_vs_full_buy',
        'average_grenade_value_lost',
        'matchmaking_rank',
    ];

    protected $casts = [];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_steam_id', 'steam_id');
    }
}
