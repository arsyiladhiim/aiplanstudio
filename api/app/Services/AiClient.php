<?php

namespace App\Services;

use App\Models\AiProvider;
use Illuminate\Support\Facades\Http;

class AiClient
{
    private ?AiProvider $provider = null;

    public string $lastFinishReason = '';

    public function __construct(?AiProvider $provider = null)
    {
        $this->provider = $provider ?? AiProvider::current();
    }

    public function isConfigured(): bool
    {
        return $this->provider !== null && ! empty($this->provider->api_key);
    }

    public function testConnection(): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Provider belum dikonfigurasi.'];
        }
        $check = $this->validateBaseUrl();
        if ($check !== null) {
            return ['ok' => false, 'message' => $check];
        }
        try {
            $res = Http::withHeaders($this->provider->authHeaders())
                ->timeout(15)
                ->post($this->provider->chatEndpoint(), $this->provider->chatBody(
                    [['role' => 'user', 'content' => 'Hi.']], 5
                ));
            if ($res->successful()) {
                return ['ok' => true, 'message' => 'Koneksi berhasil.'];
            }

            return ['ok' => false, 'message' => 'Provider mengembalikan error. Periksa konfigurasi.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal terhubung ke provider.'];
        }
    }

    public function testPrompt(string $prompt): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'message' => 'Provider belum dikonfigurasi.', 'response' => null];
        }
        $check = $this->validateBaseUrl();
        if ($check !== null) {
            return ['ok' => false, 'message' => $check, 'response' => null];
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

            return ['ok' => false, 'message' => 'Provider mengembalikan error. Periksa konfigurasi.', 'response' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal terhubung ke provider.', 'response' => null];
        }
    }

    private function parseSSEResponse(string $text): ?string
    {
        $full = '';
        foreach (explode("\n", $text) as $line) {
            if (! str_starts_with($line, 'data: ')) {
                continue;
            }
            $data = trim(substr($line, 6));
            if ($data === '[DONE]' || $data === 'done') {
                break;
            }
            $decoded = json_decode($data, true);
            if (! $decoded) {
                continue;
            }
            $delta = $this->provider->parseStreamDelta($decoded);
            if ($delta !== null) {
                $full .= $delta;
            }
            if (isset($decoded['type']) && $decoded['type'] === 'content_block_delta') {
                $full .= $decoded['delta']['text'] ?? '';
            }
        }

        return $full !== '' ? $full : null;
    }

    private function validateBaseUrl(): ?string
    {
        $url = $this->provider->base_url ?? '';
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === '') {
            return 'URL tidak valid.';
        }
        $blockedHosts = ['api', 'web', 'db', 'redis', 'nginx', 'localhost', '127.0.0.1', 'aiplanstudio_web', 'aiplanstudionginx_api', 'aiplanstudio_apifpm', 'aiplanstudio_db', 'aiplanstudio_redis', '0.0.0.0', '::1', 'host.docker.internal'];
        if (in_array(strtolower($host), $blockedHosts, true)) {
            return 'URL mengarah ke host internal. Hanya domain eksternal yang diizinkan.';
        }
        // Check if host is already a literal IP (including IPv6)
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            $ips = [$host];
        } else {
            // Resolve both IPv4 and IPv6 addresses; treat DNS errors as "unresolvable"
            try {
                $records = @dns_get_record($host, DNS_A | DNS_AAAA);
                $records = is_array($records) ? $records : [];
            } catch (\Throwable $e) {
                $records = [];
            }
            $ips = [];
            foreach ($records as $rec) {
                if (isset($rec['ip'])) {
                    $ips[] = $rec['ip'];
                }
                if (isset($rec['ipv6'])) {
                    $ips[] = $rec['ipv6'];
                }
            }
            if (empty($ips)) {
                // Unresolvable host — reject as suspicious (DNS rebinding protection)
                return 'Host tidak dapat diresolusi. Hanya domain eksternal yang diizinkan.';
            }
        }
        foreach ($ips as $ip) {
            $isInternal = filter_var($ip, FILTER_VALIDATE_IP) && (
                filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false
            );
            if ($isInternal) {
                return 'URL mengarah ke jaringan internal. Hanya domain eksternal yang diizinkan.';
            }
        }

        return null;
    }

    /**
     * Re-resolve host immediately sebelum setiap HTTP call untuk mitigasi
     * DNS rebinding TOCTOU. Jika hostname sudah punya IP address tetap,
     * kita reject jika hasil resolve beda dari baseline validasi awal.
     */
    private function ensureHostStillSafe(string $url): void
    {
        $host = parse_url($url, PHP_URL_HOST);
        if ($host === null || $host === '' || filter_var($host, FILTER_VALIDATE_IP)) {
            return;
        }
        try {
            $records = @dns_get_record($host, DNS_A | DNS_AAAA);
            $records = is_array($records) ? $records : [];
        } catch (\Throwable $e) {
            $records = [];
        }
        if (empty($records)) {
            throw new \RuntimeException('Host tidak dapat diresolusi saat runtime. Kemungkinan DNS rebinding.');
        }
        foreach ($records as $rec) {
            $ip = $rec['ip'] ?? $rec['ipv6'] ?? null;
            if ($ip === null) {
                continue;
            }
            $isInternal = ! (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE));
            if ($isInternal) {
                throw new \RuntimeException('Host resolve ke IP internal. DNS rebinding terdeteksi.');
            }
        }
    }

    public function stream(array $messages, callable $onToken): string
    {
        $this->lastFinishReason = '';

        if (! $this->isConfigured()) {
            throw new \RuntimeException('AI Provider belum dikonfigurasi.');
        }
        $check = $this->validateBaseUrl();
        if ($check !== null) {
            throw new \RuntimeException($check);
        }
        $this->ensureHostStillSafe($this->provider->base_url ?? '');
        $res = Http::withHeaders($this->provider->authHeaders())
            ->timeout(300)->withOptions(['stream' => true])
            ->post($this->provider->chatEndpoint(), $this->provider->chatBody($messages, 8192, true));

        if (! $res->successful()) {
            throw new \RuntimeException("AI Provider mengembalikan error (HTTP {$res->status()}). Periksa konfigurasi.");
        }

        $buffer = '';
        $body = $res->toPsrResponse()->getBody();
        while (! $body->eof()) {
            foreach (explode("\n", $body->read(8192)) as $chunk) {
                if (! str_starts_with($chunk, 'data: ')) {
                    continue;
                }
                $data = trim(substr($chunk, 6));
                if ($data === '[DONE]' || $data === 'done') {
                    $this->lastFinishReason = 'stop';

                    return $buffer;
                }
                $decoded = json_decode($data, true) ?? [];
                if ($this->provider->isStreamDone($decoded)) {
                    $this->lastFinishReason = 'stop';

                    return $buffer;
                }
                // Track finish_reason from OpenAI streaming chunks
                if (isset($decoded['choices'][0]['finish_reason']) && $decoded['choices'][0]['finish_reason'] !== null) {
                    $this->lastFinishReason = $decoded['choices'][0]['finish_reason'];
                }
                $content = $this->provider->parseStreamDelta($decoded);
                if ($content !== null && $content !== '') {
                    $buffer .= $content;
                    $onToken($content);
                }
            }
        }

        return $buffer;
    }

    public function complete(array $messages): string
    {
        $this->lastFinishReason = '';

        if (! $this->isConfigured()) {
            throw new \RuntimeException('AI Provider belum dikonfigurasi.');
        }
        $check = $this->validateBaseUrl();
        if ($check !== null) {
            throw new \RuntimeException($check);
        }
        $this->ensureHostStillSafe($this->provider->base_url ?? '');

        $res = Http::withHeaders($this->provider->authHeaders())
            ->timeout(120)
            ->post($this->provider->chatEndpoint(), $this->provider->chatBody($messages, 4096, false));

        if (! $res->successful()) {
            throw new \RuntimeException("Provider mengembalikan error (HTTP {$res->status()}). Periksa konfigurasi.");
        }

        $body = $res->json();
        if ($body === null) {
            // Some providers return SSE even for non-streaming — parse manually
            $bodyText = $res->body();
            $parsed = $this->parseSSEResponse($bodyText);
            if ($parsed !== null) {
                return $parsed;
            }

            return mb_substr($bodyText, 0, 2000);
        }

        return $this->provider->parseResponseContent($body) ?? '';
    }
}
