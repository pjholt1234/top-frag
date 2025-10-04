<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerMatchAimEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'player_steam_id',
        'shots_fired',
        'shots_on_hit',
        'accuracy_all_shots',
        'spraying_shots_fired',
        'spraying_shots_hit',
        'spraying_accuracy',
        'average_crosshair_placement_x',
        'average_crosshair_placement_y',
        'headshot_accuracy',
        'average_time_to_damage',
        'head_hits_total',
        'upper_chest_hits_total',
        'chest_hits_total',
        'legs_hits_total',
    ];

    protected $casts = [
        'accuracy_all_shots' => 'decimal:2',
        'spraying_accuracy' => 'decimal:2',
        'average_crosshair_placement_x' => 'decimal:4',
        'average_crosshair_placement_y' => 'decimal:4',
        'headshot_accuracy' => 'decimal:2',
        'average_reaction_time' => 'decimal:4',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }
}
