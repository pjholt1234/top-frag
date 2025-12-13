<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClanMatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'clan_id',
        'match_id',
    ];

    // Relationships
    public function clan(): BelongsTo
    {
        return $this->belongsTo(Clan::class);
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
