<?php

namespace App\Services\Research;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WebSearchClient
{
    public function __construct(
        private readonly string $provider,
        private readonly string $apiKey,
    ) {}

    /**
     * @return array<int, array{title: string, url: string, snippet: string}>
     *
     * @throws WebSearchException
     */
    public function search(string $query, int $maxResults = 5): array
    {
        if ($this->apiKey === '') {
            throw new WebSearchException('Search API key kosong');
        }

        return match ($this->provider) {
            'tavily' => $this->searchTavily($query, $maxResults),
            'brave' => $this->searchBrave($query, $maxResults),
            default => throw new WebSearchException("Search provider tidak dikenal: {$this->provider}"),
        };
    }

    private function searchTavily(string $query, int $maxResults): array
    {
        $response = $this->request(fn () => Http::timeout(20)
            ->withToken($this->apiKey)
            ->post('https://api.tavily.com/search', [
                'query' => $query,
                'max_results' => $maxResults,
                'include_answer' => false,
            ]));

        return array_map(fn (array $r) => [
            'title' => (string) ($r['title'] ?? ''),
            'url' => (string) ($r['url'] ?? ''),
            'snippet' => (string) ($r['content'] ?? ''),
        ], array_slice($response['results'] ?? [], 0, $maxResults));
    }

    private function searchBrave(string $query, int $maxResults): array
    {
        $response = $this->request(fn () => Http::timeout(20)
            ->withHeaders(['X-Subscription-Token' => $this->apiKey, 'Accept' => 'application/json'])
            ->get('https://api.search.brave.com/res/v1/web/search', [
                'q' => $query,
                'count' => $maxResults,
            ]));

        return array_map(fn (array $r) => [
            'title' => (string) ($r['title'] ?? ''),
            'url' => (string) ($r['url'] ?? ''),
            'snippet' => (string) ($r['description'] ?? ''),
        ], array_slice($response['web']['results'] ?? [], 0, $maxResults));
    }

    private function request(callable $fn): array
    {
        try {
            $response = $fn();
        } catch (ConnectionException $e) {
            throw new WebSearchException('Koneksi search gagal: '.$e->getMessage());
        }

        if ($response->failed()) {
            throw new WebSearchException("Search HTTP {$response->status()}: ".$response->body());
        }

        return $response->json() ?? [];
    }
}
