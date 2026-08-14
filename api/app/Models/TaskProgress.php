<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['phase_progress_id', 'task_key', 'task_type', 'title', 'status', 'checkpoint', 'output', 'started_at', 'finished_at'])]
class TaskProgress extends Model
{
    protected $casts = [
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function phaseProgress(): BelongsTo
    {
        return $this->belongsTo(PhaseProgress::class);
    }
}
