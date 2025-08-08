<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchSummary extends Model
{
    use HasFactory;

    protected $table = 'match_summaries';

    protected $fillable = [
        'match_id',
        'total_kills',
        'total_deaths',
        'total_assists',
        'total_headshots',
        'total_wallbangs',
        'total_damage',
        'total_he_damage',
        'total_effective_flashes',
        'total_smokes_used',
        'total_molotovs_used',
        'total_first_kills',
        'total_first_deaths',
        'total_clutches_1v1_attempted',
        'total_clutches_1v1_successful',
        'total_clutches_1v2_attempted',
        'total_clutches_1v2_successful',
        'total_clutches_1v3_attempted',
        'total_clutches_1v3_successful',
        'total_clutches_1v4_attempted',
        'total_clutches_1v4_successful',
        'total_clutches_1v5_attempted',
        'total_clutches_1v5_successful',
    ];

    protected $casts = [
        'total_kills' => 'integer',
        'total_deaths' => 'integer',
        'total_assists' => 'integer',
        'total_headshots' => 'integer',
        'total_wallbangs' => 'integer',
        'total_damage' => 'integer',
        'total_he_damage' => 'integer',
        'total_effective_flashes' => 'integer',
        'total_smokes_used' => 'integer',
        'total_molotovs_used' => 'integer',
        'total_first_kills' => 'integer',
        'total_first_deaths' => 'integer',
        'total_clutches_1v1_attempted' => 'integer',
        'total_clutches_1v1_successful' => 'integer',
        'total_clutches_1v2_attempted' => 'integer',
        'total_clutches_1v2_successful' => 'integer',
        'total_clutches_1v3_attempted' => 'integer',
        'total_clutches_1v3_successful' => 'integer',
        'total_clutches_1v4_attempted' => 'integer',
        'total_clutches_1v4_successful' => 'integer',
        'total_clutches_1v5_attempted' => 'integer',
        'total_clutches_1v5_successful' => 'integer',
    ];

    // Relationships
    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
