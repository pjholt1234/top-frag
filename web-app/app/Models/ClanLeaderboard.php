<?php

namespace App\Models;

use App\Enums\LeaderboardType;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanLeaderboard extends Model
{
    use HasFactory;

    protected $fillable = [
        'clan_id',
        'start_date',
        'end_date',
        'leaderboard_type',
        'user_id',
        'position',
        'value',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'leaderboard_type' => LeaderboardType::class,
        'position' => 'integer',
        'value' => 'decimal:2',
    ];

    // Relationships
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes
    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('leaderboard_type', $type);
    }

    public function scopeByPeriod(Builder $query, Carbon $start, Carbon $end): Builder
    {
        return $query->where('start_date', $start->format('Y-m-d'))
            ->where('end_date', $end->format('Y-m-d'));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('position', 'asc');
    }
}
