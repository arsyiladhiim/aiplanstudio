<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'version_no', 'stage_status', 'analysis', 'prd',
    'architecture', 'erd', 'api_contract', 'phases', 'master_prompt',
])]
class Version extends Model
{
    protected function casts(): array
    {
        return [
            'stage_status' => 'array',
            'erd' => 'array',
            'api_contract' => 'array',
            'phases' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function phaseProgress(): HasMany
    {
        return $this->hasMany(PhaseProgress::class);
    }

    /** Status default sebelum pipeline dijalankan. */
    public static function defaultStageStatus(): array
    {
        return [
            'analisa' => 'pending',
            'prd' => 'pending',
            'architecture' => 'pending',
            'erd' => 'pending',
            'phases' => 'pending',
            'master' => 'pending',
        ];
    }
}
