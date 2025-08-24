<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GrenadeFavourite extends Model
{
    use HasFactory;

    protected $table = 'grenade_favourites';

    protected $fillable = [
        'match_id',
        'user_id',
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
        'flash_duration',
        'friendly_flash_duration',
        'enemy_flash_duration',
        'friendly_players_affected',
        'enemy_players_affected',
        'throw_type',
        'effectiveness_rating',
    ];

    public function match()
    {
        return $this->belongsTo(GameMatch::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
