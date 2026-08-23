<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VersionStageEvidence extends Model
{
    use HasFactory;

    protected $table = 'version_stage_evidence';

    protected $fillable = [
        'project_id', 'version_id', 'stage_key', 'task_key',
        'files_changed', 'tests_passed', 'lint_passed', 'build_passed',
        'migrate_passed', 'security_passed', 'perf_passed',
        'evidence_url', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'files_changed' => 'array',
            'tests_passed' => 'boolean',
            'lint_passed' => 'boolean',
            'build_passed' => 'boolean',
            'migrate_passed' => 'boolean',
            'security_passed' => 'boolean',
            'perf_passed' => 'boolean',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
