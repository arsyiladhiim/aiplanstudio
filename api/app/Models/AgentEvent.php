<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** CP-44 CP-07: Agent Event Protocol v1. */
#[Fillable(['project_id', 'version_id', 'run_id', 'event_id', 'event', 'phase_key', 'task_key', 'status', 'payload'])]
class AgentEvent extends Model
{
    protected $table = 'aiplanstudio_project.agent_events';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }

    /** Round-1 event whitelist. */
    public const EVENTS = [
        'agent.started',
        'heartbeat',
        'phase.started',
        'phase.completed',
        'task.started',
        'task.completed',
        'task.failed',
        'blocked',
        'agent.completed',
        'agent.failed',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
