<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'version_no', 'stage_status', 'pertanyaan', 'answers', 'analysis', 'prd',
    'architecture', 'erd', 'api_contract', 'phases', 'master_prompt',
    'standards', 'agents', 'tracking_token',
    'mobile_phases', 'mobile_master_prompt', 'mobile_standards', 'mobile_agents',
])]
class Version extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'stage_status' => 'array',
            'answers' => 'array',
            'erd' => 'array',
            'api_contract' => 'array',
            'phases' => 'array',
            'mobile_phases' => 'array',
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
            'pertanyaan' => 'pending',
            'analisa' => 'pending',
            'prd' => 'pending',
            'architecture' => 'pending',
            'erd' => 'pending',
            'standards_web' => 'pending',
            'agents_web' => 'pending',
            'phases_web' => 'pending',
            'master_web' => 'pending',
            'phases_mobile' => 'pending',
            'standards_mobile' => 'pending',
            'agents_mobile' => 'pending',
            'master_mobile' => 'pending',
        ];
    }
}
