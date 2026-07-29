<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

#[Fillable(['title', 'idea', 'target', 'stack'])]
class Project extends Model
{
    use HasFactory;
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class)->orderByDesc('version_no')->take(50);
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
}
