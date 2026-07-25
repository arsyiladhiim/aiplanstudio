<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['version_id', 'phase_key', 'done'])]
class PhaseProgress extends Model
{
    protected function casts(): array
    {
        return ['done' => 'boolean'];
    }

    public function version(): BelongsTo
    {
        return $this->belongsTo(Version::class);
    }
}
