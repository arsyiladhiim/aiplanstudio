<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'base_url', 'api_key', 'model', 'provider_type', 'is_active', 'last_test_response', 'last_test_at'])]
#[Hidden(['api_key'])]
class AiProvider extends Model
{
    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
            'last_test_at' => 'datetime',
        ];
    }

    public static function current(): ?self
    {
        return static::query()->where('is_active', true)->first();
    }

    public function maskedKey(): string
    {
        $key = $this->api_key ?? '';
        if (strlen($key) <= 8) return $key === '' ? '' : str_repeat('•', strlen($key));
        return substr($key, 0, 3) . str_repeat('•', 6) . substr($key, -4);
    }

    public function authHeaders(): array
    {
        return match ($this->provider_type) {
            'anthropic' => ['x-api-key' => $this->api_key ?? '', 'anthropic-version' => '2023-06-01'],
            default => ['Authorization' => 'Bearer ' . ($this->api_key ?? '')],
        };
    }

    public function chatEndpoint(): string
    {
        $base = rtrim($this->base_url, '/');
        return match ($this->provider_type) {
            'anthropic' => "{$base}/messages",
            default => "{$base}/chat/completions",
        };
    }

    public function chatBody(array $messages, int $maxTokens = 4096, bool $stream = false): array
    {
        $body = match ($this->provider_type) {
            'anthropic' => [
                'model' => $this->model, 'max_tokens' => $maxTokens,
                'messages' => $messages, 'stream' => $stream,
            ],
            default => [
                'model' => $this->model, 'messages' => $messages,
                'max_tokens' => $maxTokens, 'stream' => $stream,
            ],
        };
        if (!$stream) unset($body['stream']);
        return $body;
    }

    public function parseResponseContent(array $body): ?string
    {
        return match ($this->provider_type) {
            'anthropic' => $body['content'][0]['text'] ?? null,
            default => $body['choices'][0]['message']['content'] ?? null,
        };
    }

    public function parseStreamDelta(array $body): ?string
    {
        return match ($this->provider_type) {
            'anthropic' => $body['delta']['text'] ?? $body['content_block']['text'] ?? null,
            default => $body['choices'][0]['delta']['content'] ?? null,
        };
    }

    public function isStreamDone(array $body): bool
    {
        return match ($this->provider_type) {
            'anthropic' => ($body['type'] ?? null) === 'message_stop',
            default => false,
        };
    }
}
