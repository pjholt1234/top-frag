<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoundEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'round_number',
        'tick_timestamp',
        'event_type',
        'winner',
        'duration',
        'total_impact',
        'total_gunfights',
        'average_impact',
        'round_swing_percent',
        'impact_percentage',
    ];

    protected $casts = [
        'total_impact' => 'decimal:2',
        'average_impact' => 'decimal:2',
        'round_swing_percent' => 'decimal:2',
        'impact_percentage' => 'decimal:2',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
