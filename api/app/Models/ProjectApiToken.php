<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'name', 'token_hash', 'expires_at'])]
class ProjectApiToken extends Model
{
    protected $table = 'aiplanstudio_project.project_api_tokens';

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'last_used_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public static function generate(Project $project, string $name, ?\DateTime $expiresAt = null): array
    {
        $raw = bin2hex(random_bytes(32));
        $token = self::create([
            'project_id' => $project->id,
            'name' => $name,
            'token_hash' => hash('sha256', $raw),
            'expires_at' => $expiresAt,
        ]);
        return ['token' => $raw, 'model' => $token];
    }
}
