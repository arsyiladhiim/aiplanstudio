<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['enabled', 'search_provider', 'ai_provider_id', 'max_per_day', 'last_run_at', 'last_run_status'])]
#[Hidden(['search_api_key'])]
class ResearchAgentSettings extends Model
{
    protected $table = 'aiplanstudio_settings.research_agent_settings';

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function setSearchApiKeyAttribute(?string $value): void
    {
        if ($value !== null && $value !== '') {
            $this->attributes['search_api_key'] = Crypt::encryptString($value);
        }
    }

    public function decryptedSearchKey(): string
    {
        $raw = $this->getRawOriginal('search_api_key');

        return $raw ? Crypt::decryptString($raw) : '';
    }

    public function maskedSearchKey(): string
    {
        $key = $this->decryptedSearchKey();
        if ($key === '') {
            return '';
        }

        return strlen($key) <= 8 ? str_repeat('•', strlen($key))
            : substr($key, 0, 3).str_repeat('•', 6).substr($key, -4);
    }

    public function aiProvider(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(AiProvider::class, 'ai_provider_id');
    }

    public static function singleton(): self
    {
        return static::query()->firstOrCreate([]);
    }

    public function isConfigured(): bool
    {
        return $this->ai_provider_id !== null && $this->decryptedSearchKey() !== '';
    }

    public function isReady(): bool
    {
        return $this->enabled && $this->isConfigured();
    }
}
