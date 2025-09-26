<?php

namespace App\Models;

use App\Enums\GrenadeType;
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
        'player_side',
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
        'team_damage_dealt',
        'friendly_flash_duration',
        'enemy_flash_duration',
        'friendly_players_affected',
        'enemy_players_affected',
        'throw_type',
        'effectiveness_rating',
        'flash_leads_to_kill',
        'flash_leads_to_death',
        'smoke_blocking_duration',
    ];

    protected $casts = [
        'round_number' => 'integer',
        'round_time' => 'integer',
        'tick_timestamp' => 'integer',
        'player_side' => 'string',
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
        'team_damage_dealt' => 'integer',
        'friendly_flash_duration' => 'float',
        'enemy_flash_duration' => 'float',
        'friendly_players_affected' => 'integer',
        'enemy_players_affected' => 'integer',
        'throw_type' => 'string',
        'effectiveness_rating' => 'integer',
        'flash_leads_to_kill' => 'boolean',
        'flash_leads_to_death' => 'boolean',
        'smoke_blocking_duration' => 'integer',
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

    /**
     * Generate a position string in the format: setpos {player_x} {player_y} {player_z};setang {player_aim_x} {player_aim_y} 0.000000
     */
    public function generatePositionString(): string
    {
        return sprintf(
            'setpos %.6f %.6f %.6f;setang %.6f %.6f 0.000000',
            $this->player_x,
            $this->player_y,
            $this->player_z,
            $this->player_aim_y,
            $this->player_aim_x
        );
    }
}
