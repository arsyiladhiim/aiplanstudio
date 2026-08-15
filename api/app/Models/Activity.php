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
    public const ACTION_USER_APPROVED = 'user_approved';          // CP-16.M3
    public const ACTION_USER_REJECTED = 'user_rejected';          // CP-16.M3
    public const ACTION_USER_DELETED = 'user_deleted';            // CP-16.M3
    public const ACTION_USER_REGISTERED = 'user_registered';      // CP-17.L3
    public const ACTION_USER_LOGIN = 'user_login';                // CP-17.L3
    public const ACTION_USER_FAILED_LOGIN = 'user_failed_login';  // CP-17.L3
    public const ACTION_USER_PASSWORD_RESET = 'user_password_reset'; // CP-17.L3
    public const ACTION_TWO_FACTOR_ENABLED = 'two_factor_enabled'; // CP-18.F1
    public const ACTION_TWO_FACTOR_DISABLED = 'two_factor_disabled'; // CP-18.F1

    public const ACTIONS = [
        self::ACTION_CREATED_VERSION,
        self::ACTION_DELETED_VERSION,
        self::ACTION_CREATED_PROJECT,
        self::ACTION_UPDATED_PROJECT,
        self::ACTION_DELETED_PROJECT,
        self::ACTION_REGENERATE_STAGE,
        self::ACTION_ARTIFACT_SNAPSHOT,
        self::ACTION_WEBHOOK_RECEIVED,
        self::ACTION_USER_APPROVED,
        self::ACTION_USER_REJECTED,
        self::ACTION_USER_DELETED,
        self::ACTION_USER_REGISTERED,
        self::ACTION_USER_LOGIN,
        self::ACTION_USER_FAILED_LOGIN,
        self::ACTION_USER_PASSWORD_RESET,
        self::ACTION_TWO_FACTOR_ENABLED,
        self::ACTION_TWO_FACTOR_DISABLED,
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
