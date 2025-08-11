<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GunfightEvent extends Model
{
    use HasFactory;

    protected $table = 'gunfight_events';

    protected $fillable = [
        'match_id',
        'round_number',
        'round_time',
        'tick_timestamp',
        'player_1_steam_id',
        'player_2_steam_id',
        'player_1_hp_start',
        'player_2_hp_start',
        'player_1_armor',
        'player_2_armor',
        'player_1_flashed',
        'player_2_flashed',
        'player_1_weapon',
        'player_2_weapon',
        'player_1_equipment_value',
        'player_2_equipment_value',
        'player_1_x',
        'player_1_y',
        'player_1_z',
        'player_2_x',
        'player_2_y',
        'player_2_z',
        'distance',
        'headshot',
        'wallbang',
        'penetrated_objects',
        'victor_steam_id',
        'damage_dealt',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'round_time' => 'integer',
        'tick_timestamp' => 'integer',
        'player_1_hp_start' => 'integer',
        'player_2_hp_start' => 'integer',
        'player_1_armor' => 'integer',
        'player_2_armor' => 'integer',
        'player_1_flashed' => 'boolean',
        'player_2_flashed' => 'boolean',
        'player_1_equipment_value' => 'integer',
        'player_2_equipment_value' => 'integer',
        'player_1_x' => 'float',
        'player_1_y' => 'float',
        'player_1_z' => 'float',
        'player_2_x' => 'float',
        'player_2_y' => 'float',
        'player_2_z' => 'float',
        'distance' => 'float',
        'headshot' => 'boolean',
        'wallbang' => 'boolean',
        'penetrated_objects' => 'integer',
        'damage_dealt' => 'integer',
    ];

    // Relationships
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player1(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_1_steam_id', 'steam_id');
    }

    public function player2(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_2_steam_id', 'steam_id');
    }

    public function victor(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'victor_steam_id', 'steam_id');
    }
}
