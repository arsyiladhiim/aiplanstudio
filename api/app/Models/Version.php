<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'version_no', 'stage_status', 'stage_tokens', 'pertanyaan', 'answers', 'pertanyaan_mobile', 'mobile_answers', 'analysis', 'prd',
    'architecture', 'erd', 'api_contract', 'phases', 'master_prompt',
    'design_system', 'design_system_mobile', 'app_spec_web', 'app_spec_mobile',
    'standards', 'agents', 'tracking_token',
    'mobile_phases', 'mobile_master_prompt', 'mobile_standards', 'mobile_agents',
    'env_config', 'security', 'deployment', 'observability',
    'source_version_id', 'baseline_notes', 'skip_reasons', 'stage_quality', 'stage_errors',
])]
class Version extends Model
{
    use HasFactory;

    public const ALL_STAGES = [
        'pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract',
        'design_system',
        'phases_web', 'standards_web', 'master_web',
        'app_spec_web',
        'design_system_mobile',
        'pertanyaan_mobile', 'standards_mobile', 'phases_mobile', 'master_mobile',
        'app_spec_mobile',
        'env_config', 'security', 'deployment', 'observability',
        'agents',
    ];

    protected function casts(): array
    {
        return [
            'stage_status' => 'array',
            'stage_quality' => 'array',
            'stage_errors' => 'array',
            'stage_tokens' => 'array',
            'skip_reasons' => 'array',
            'answers' => 'array',
            'mobile_answers' => 'array',
            'erd' => 'array',
            'api_contract' => 'array',
            'phases' => 'array',
            'mobile_phases' => 'array',
            'app_spec_web' => 'array',
            'app_spec_mobile' => 'array',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(Version::class, 'source_version_id');
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
            'api_contract' => 'pending',
            'design_system' => 'pending',
            'phases_web' => 'pending',
            'standards_web' => 'pending',
            'master_web' => 'pending',
            'app_spec_web' => 'pending',
            'design_system_mobile' => 'pending',
            'pertanyaan_mobile' => 'pending',
            'standards_mobile' => 'pending',
            'phases_mobile' => 'pending',
            'master_mobile' => 'pending',
            'app_spec_mobile' => 'pending',
            'env_config' => 'pending',
            'security' => 'pending',
            'deployment' => 'pending',
            'observability' => 'pending',
            'agents' => 'pending',
        ];
    }

    /**
     * Count of completed stages, normalized to the project target.
     * Web projects ignore mobile stages. Both counts all visible stages.
     */
    public function progressCount(): int
    {
        if (! $this->stage_status) {
            return 0;
        }

        $stages = collect(self::ALL_STAGES)
            ->when(
                $this->project?->target !== 'both',
                fn ($c) => $c->reject(fn ($s) => str_contains($s, 'mobile')),
            );

        return collect($this->stage_status)
            ->filter(fn ($status, $key) => $stages->contains($key) && $status === 'done')
            ->count();
    }

    /**
     * Total visible stages for this project's target (web excludes mobile).
     */
    public function visibleStageCount(): int
    {
        return collect(self::ALL_STAGES)
            ->when(
                $this->project?->target !== 'both',
                fn ($c) => $c->reject(fn ($s) => str_contains($s, 'mobile')),
            )
            ->count();
    }
}
