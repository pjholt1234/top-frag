<?php

namespace App\Models;

use App\Enums\AchievementType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Achievement extends Model
{
    use HasFactory;

    protected $fillable = [
        'match_id',
        'player_id',
        'award_name',
    ];

    protected $casts = [
        'award_name' => AchievementType::class,
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
