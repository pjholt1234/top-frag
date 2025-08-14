<?php

namespace App\Models;

use App\Enums\ProcessingStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DemoProcessingJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'match_id',
        'processing_status',
        'progress_percentage',
        'error_message',
        'started_at',
        'completed_at',
        'current_step',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'processing_status' => ProcessingStatus::class,
        'progress_percentage' => 'integer',
        'match_id' => 'integer',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }
}
