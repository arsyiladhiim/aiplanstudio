<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'project_id', 'version_no', 'stage_status', 'pertanyaan', 'answers', 'pertanyaan_mobile', 'mobile_answers', 'analysis', 'prd',
    'architecture', 'erd', 'api_contract', 'phases', 'master_prompt',
    'standards', 'agents', 'tracking_token',
    'mobile_phases', 'mobile_master_prompt', 'mobile_standards', 'mobile_agents',
    'source_version_id', 'baseline_notes',
])]
class Version extends Model
{
    use HasFactory;

    public const ALL_STAGES = [
        'pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract',
        'phases_web', 'standards_web', 'master_web',
        'pertanyaan_mobile',
        'phases_mobile', 'standards_mobile', 'master_mobile',
        'agents',
    ];

    protected function casts(): array
    {
        return [
            'stage_status' => 'array',
            'answers' => 'array',
            'mobile_answers' => 'array',
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
            'phases_web' => 'pending',
            'standards_web' => 'pending',
            'master_web' => 'pending',
            'pertanyaan_mobile' => 'pending',
            'phases_mobile' => 'pending',
            'standards_mobile' => 'pending',
            'master_mobile' => 'pending',
            'agents' => 'pending',
        ];
    }

    /** Count of completed stages. */
    public function progressCount(): int
    {
        if (! $this->stage_status) return 0;
        return collect($this->stage_status)->filter(fn ($s) => $s === 'done')->count();
    }
}
