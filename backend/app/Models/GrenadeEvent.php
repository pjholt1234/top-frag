<?php

namespace App\Models;

use App\Enums\GrenadeType;
use App\Enums\ThrowType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrenadeEvent extends Model
{
    use HasFactory;

    protected $table = 'grenade_events';

    protected $fillable = [
        'match_id',
        'round_number',
        'round_time',
        'tick_timestamp',
        'player_steam_id',
        'grenade_type',
        'player_x',
        'player_y',
        'player_z',
        'player_aim_x',
        'player_aim_y',
        'player_aim_z',
        'grenade_final_x',
        'grenade_final_y',
        'grenade_final_z',
        'damage_dealt',
        'flash_duration',
        'affected_players',
        'throw_type',
        'effectiveness_rating',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'round_time' => 'integer',
        'tick_timestamp' => 'integer',
        'grenade_type' => GrenadeType::class,
        'player_x' => 'float',
        'player_y' => 'float',
        'player_z' => 'float',
        'player_aim_x' => 'float',
        'player_aim_y' => 'float',
        'player_aim_z' => 'float',
        'grenade_final_x' => 'float',
        'grenade_final_y' => 'float',
        'grenade_final_z' => 'float',
        'damage_dealt' => 'integer',
        'flash_duration' => 'float',
        'affected_players' => 'array',
        'throw_type' => ThrowType::class,
        'effectiveness_rating' => 'integer',
    ];

    // Relationships
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_steam_id', 'steam_id');
    }
}
