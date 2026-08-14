<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'version_id', 'user_id', 'action', 'description', 'metadata'])]
class Activity extends Model
{
    public const ACTION_CREATED_VERSION = 'created_version';
    public const ACTION_DELETED_VERSION = 'deleted_version';
    public const ACTION_CREATED_PROJECT = 'created_project';
    public const ACTION_UPDATED_PROJECT = 'updated_project';
    public const ACTION_DELETED_PROJECT = 'deleted_project';
    public const ACTION_REGENERATE_STAGE = 'regenerate_stage';
    public const ACTION_ARTIFACT_SNAPSHOT = 'artifact_snapshot';
    public const ACTION_WEBHOOK_RECEIVED = 'webhook_received';

    public const ACTIONS = [
        self::ACTION_CREATED_VERSION,
        self::ACTION_DELETED_VERSION,
        self::ACTION_CREATED_PROJECT,
        self::ACTION_UPDATED_PROJECT,
        self::ACTION_DELETED_PROJECT,
        self::ACTION_REGENERATE_STAGE,
        self::ACTION_ARTIFACT_SNAPSHOT,
        self::ACTION_WEBHOOK_RECEIVED,
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
