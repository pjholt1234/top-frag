<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMatchSummary extends Model
{
    use HasFactory;

    protected $table = 'player_match_summaries';

    protected $fillable = [
        'match_id',
        'player_id',
        'kills',
        'deaths',
        'assists',
        'headshots',
        'wallbangs',
        'first_kills',
        'first_deaths',
        'total_damage',
        'average_damage_per_round',
        'damage_taken',
        'he_damage',
        'effective_flashes',
        'smokes_used',
        'molotovs_used',
        'flashbangs_used',
        'clutches_1v1_attempted',
        'clutches_1v1_successful',
        'clutches_1v2_attempted',
        'clutches_1v2_successful',
        'clutches_1v3_attempted',
        'clutches_1v3_successful',
        'clutches_1v4_attempted',
        'clutches_1v4_successful',
        'clutches_1v5_attempted',
        'clutches_1v5_successful',
        'kd_ratio',
        'headshot_percentage',
        'clutch_success_rate',
    ];

    protected $casts = [
        'kills' => 'integer',
        'deaths' => 'integer',
        'assists' => 'integer',
        'headshots' => 'integer',
        'wallbangs' => 'integer',
        'first_kills' => 'integer',
        'first_deaths' => 'integer',
        'total_damage' => 'integer',
        'average_damage_per_round' => 'float',
        'damage_taken' => 'integer',
        'he_damage' => 'integer',
        'effective_flashes' => 'integer',
        'smokes_used' => 'integer',
        'molotovs_used' => 'integer',
        'flashbangs_used' => 'integer',
        'clutches_1v1_attempted' => 'integer',
        'clutches_1v1_successful' => 'integer',
        'clutches_1v2_attempted' => 'integer',
        'clutches_1v2_successful' => 'integer',
        'clutches_1v3_attempted' => 'integer',
        'clutches_1v3_successful' => 'integer',
        'clutches_1v4_attempted' => 'integer',
        'clutches_1v4_successful' => 'integer',
        'clutches_1v5_attempted' => 'integer',
        'clutches_1v5_successful' => 'integer',
        'kd_ratio' => 'float',
        'headshot_percentage' => 'float',
        'clutch_success_rate' => 'float',
    ];

    // Relationships
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }
}
