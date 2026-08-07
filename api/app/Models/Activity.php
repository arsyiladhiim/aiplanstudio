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

    public const ACTIONS = [
        self::ACTION_CREATED_VERSION,
        self::ACTION_DELETED_VERSION,
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
