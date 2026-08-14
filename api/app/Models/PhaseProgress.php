<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['version_id', 'phase_key', 'status', 'done', 'output', 'started_at', 'finished_at'])]
class PhaseProgress extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'done' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }

    public function taskProgress(): HasMany
    {
        return $this->hasMany(TaskProgress::class);
    }
}
