<?php

namespace App\Models;

use App\Enums\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchPlayer extends Model
{
    use HasFactory;

    protected $table = 'match_players';

    protected $fillable = [
        'match_id',
        'player_id',
        'team',
        'side_start',
    ];

    protected $casts = [
        'team' => Team::class,
        'side_start' => Team::class,
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
