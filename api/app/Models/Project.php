<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['title', 'idea', 'target', 'stack', 'is_favorite', 'is_pinned', 'archived_at'])]
class Project extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'is_favorite' => 'boolean',
            'is_pinned' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class)->orderByDesc('version_no');
    }

    public function versionsUnbounded(): HasMany
    {
        return $this->hasMany(Version::class)->orderByDesc('version_no');
    }

    public function apiTokens(): HasMany
    {
        return $this->hasMany(ProjectApiToken::class);
    }

    public function latestVersion(): ?Version
    {
        return $this->versions()->first();
    }

    public function nextVersionNo(): int
    {
        return ($this->versions()->max('version_no') ?? 0) + 1;
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class)->latest();
    }

    public function logActivity(string $action, string $description, ?int $versionId = null, ?array $metadata = null): Activity
    {
        return $this->activities()->create([
            'user_id' => auth()->id(),
            'version_id' => $versionId,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
