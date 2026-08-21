<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

#[Fillable(['window_date', 'title', 'target_users', 'problem', 'solution', 'sources'])]
class ResearchIdea extends Model
{
    protected $table = 'aiplanstudio_settings.research_ideas';

    protected function casts(): array
    {
        return [
            'window_date' => 'date',
            'sources' => 'array',
        ];
    }

    public static function currentWindowDate(Carbon $now): string
    {
        return $now->copy()->subHours(6)->subMinutes(30)->toDateString();
    }

    public static function countForWindow(string $windowDate): int
    {
        return static::query()->where('window_date', $windowDate)->count();
    }
}
