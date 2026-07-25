<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;

class AiClient
{
    private ?AiProvider $provider = null;

    public function __construct(?AiProvider $provider = null)
    {
        $this->provider = $provider ?? AiProvider::current();
    }

    public function isConfigured(): bool
    {
        return $this->provider !== null && !empty($this->provider->api_key);
    }

    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Provider belum dikonfigurasi.'];
        }
        try {
            $res = Http::withHeaders($this->provider->authHeaders())
                ->timeout(15)
                ->post($this->provider->chatEndpoint(), $this->provider->chatBody(
                    [['role' => 'user', 'content' => 'Hi.']], 5
                ));
            if ($res->successful()) return ['ok' => true, 'message' => 'Koneksi berhasil.'];
            return ['ok' => false, 'message' => $res->json('error.message') ?? "HTTP {$res->status()}"];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
    }

    public function testPrompt(string $prompt): array
    {
        if (!$this->isConfigured()) {
            return ['ok' => false, 'message' => 'Provider belum dikonfigurasi.', 'response' => null];
        }
        try {
            $res = Http::withHeaders($this->provider->authHeaders())
                ->timeout(30)
                ->post($this->provider->chatEndpoint(), $this->provider->chatBody(
                    [['role' => 'user', 'content' => $prompt]]
                ));
            if ($res->successful()) {
                $body = $res->json();
                if ($body === null) {
                    $bodyText = $res->body();
                    $content = $this->parseSSEResponse($bodyText);
                    return ['ok' => true, 'message' => 'Prompt berhasil.', 'response' => $content ?: mb_substr($bodyText, 0, 2000)];
                }
                $content = $this->provider->parseResponseContent($body) ?? '(empty response)';
                return ['ok' => true, 'message' => 'Prompt berhasil.', 'response' => $content];
            }
            return ['ok' => false, 'message' => $res->json('error.message') ?? "HTTP {$res->status()}", 'response' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'response' => null];
        }
    }

    /** Parse SSE (Server-Sent Events) response — extract content dari streaming chunks. */
    private function parseSSEResponse(string $text): ?string
    {
        $full = '';
        foreach (explode("\n", $text) as $line) {
            if (!str_starts_with($line, 'data: ')) continue;
            $data = trim(substr($line, 6));
            if ($data === '[DONE]' || $data === 'done') break;
            $decoded = json_decode($data, true);
            if (!$decoded) continue;
            $delta = $this->provider->parseStreamDelta($decoded);
            if ($delta !== null) $full .= $delta;
            // Anthropic: content_block_delta
            if (isset($decoded['type']) && $decoded['type'] === 'content_block_delta') {
                $full .= $decoded['delta']['text'] ?? '';
            }
        }
        return $full !== '' ? $full : null;
    }

    public function stream(array $messages, callable $onToken): void
    {
        if (!$this->isConfigured()) {
            throw new \RuntimeException('AI Provider belum dikonfigurasi.');
        }
        $res = Http::withHeaders($this->provider->authHeaders())
            ->timeout(300)->withOptions(['stream' => true])
            ->post($this->provider->chatEndpoint(), $this->provider->chatBody($messages, 200, true));

        if (!$res->successful()) {
            throw new \RuntimeException("AI Provider error: " . ($res->json('error.message') ?? "HTTP {$res->status()}"));
        }

        $body = $res->toPsrResponse()->getBody();
        while (!$body->eof()) {
            foreach (explode("\n", $body->read(8192)) as $chunk) {
                if (!str_starts_with($chunk, 'data: ')) continue;
                $data = trim(substr($chunk, 6));
                if ($data === '[DONE]' || $data === 'done') return;
                if ($this->provider->isStreamDone(json_decode($data, true) ?? [])) return;
                $content = $this->provider->parseStreamDelta(json_decode($data, true) ?? []);
                if ($content !== null && $content !== '') $onToken($content);
            }
        }
    }
}
