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
        'user_id',
        'step_progress',
        'total_steps',
        'current_step_num',
        'start_time',
        'last_update_time',
        'error_code',
        'context',
        'is_final',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'processing_status' => ProcessingStatus::class,
        'progress_percentage' => 'integer',
        'match_id' => 'integer',
        'step_progress' => 'integer',
        'total_steps' => 'integer',
        'current_step_num' => 'integer',
        'start_time' => 'datetime',
        'last_update_time' => 'datetime',
        'context' => 'array',
        'is_final' => 'boolean',
    ];

    public function match(): BelongsTo
    {
        return $this->belongsTo(GameMatch::class, 'match_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
