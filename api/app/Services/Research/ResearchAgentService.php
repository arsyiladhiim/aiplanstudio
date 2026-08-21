<?php

namespace App\Services\Research;

use App\Models\ResearchAgentSettings;
use App\Models\ResearchIdea;
use App\Services\AiClient;
use App\Services\AiJsonParser;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class ResearchAgentService
{
    public function __construct(private readonly AiJsonParser $parser = new AiJsonParser) {}

    /**
     * Jalankan satu siklus research. Return ringkasan status run.
     */
    public function collect(bool $force = false): array
    {
        $settings = ResearchAgentSettings::singleton();

        if (! $settings->isConfigured()) {
            return $this->finish($settings, 'not_configured', 0);
        }
        if (! $force && ! $settings->enabled) {
            return $this->finish($settings, 'disabled', 0);
        }

        $window = ResearchIdea::currentWindowDate(Carbon::now());
        $needed = $settings->max_per_day - ResearchIdea::countForWindow($window);
        if ($needed <= 0) {
            return $this->finish($settings, 'quota_full', 0);
        }

        try {
            $ai = new AiClient($settings->aiProvider);
            $query = $this->generateQuery($ai);
            $results = (new WebSearchClient($settings->search_provider, $settings->decryptedSearchKey()))
                ->search($query, 5);
            if ($results === []) {
                return $this->finish($settings, "no_results: {$query}", 0);
            }
            $ideas = $this->generateIdeas($ai, $query, $results, $needed);
            $created = $this->persist($ideas, $window, $results);

            return $this->finish($settings, $created > 0 ? "ok: {$created} ide" : "no_new_ideas (dup)", $created);
        } catch (\Throwable $e) {
            Log::warning('research-agent: run failed', ['error' => $e->getMessage()]);

            return $this->finish($settings, 'error: '.$e->getMessage(), 0);
        }
    }

    private function generateQuery(AiClient $ai): string
    {
        $date = Carbon::now()->toDateString();
        $out = $ai->complete([[
            'role' => 'user',
            'content' => implode("\n", [
                "Hari ini {$date}. Pilih SATU query web search (bahasa Inggris, max 12 kata) untuk menemukan trend/masalah nyata di suatu bidang (UMKM, kesehatan, pendidikan, logistik, agrikultur, pemerintahan, retail, dll) yang bisa didigitalisasi menjadi produk software. Acak bidangnya setiap kali. Balas HANYA query-nya, tanpa tanda kutip/teks lain.",
            ]),
        ]], 64);

        return trim(strtok($out, "\n")) ?: 'small business digitalization pain points trends';
    }

    private function generateIdeas(AiClient $ai, string $query, array $results, int $needed): array
    {
        $snippets = collect($results)
            ->map(fn ($r, $i) => ($i + 1).". {$r['title']} — {$r['snippet']} ({$r['url']})")
            ->implode("\n");

        $out = $ai->complete([[
            'role' => 'user',
            'content' => <<<PROMPT
Berdasarkan hasil web search tentang "{$query}" berikut:

{$snippets}

Buat TEPAT {$needed} ide produk digitalisasi baru yang menjawab kendala nyata dari temuan tersebut. Balas HANYA JSON array valid, tiap elemen:
{"title": string (unik, spesifik), "target_users": string, "problem": string (kendala/case nyata), "solution": string (ringkasan implementasi/cara mengatasi, 2-3 kalimat)}
Tanpa teks lain di luar JSON.
PROMPT,
        ]], 4096);

        $decoded = $this->parser->tryJsonDecode($this->parser->extractJson($out));

        return is_array($decoded) ? $decoded : [];
    }

    private function persist(array $ideas, string $window, array $results): int
    {
        $sources = array_map(fn ($r) => ['title' => $r['title'], 'url' => $r['url']], $results);
        $created = 0;

        foreach (array_slice($ideas, 0, 50) as $idea) {
            if (! is_array($idea) || empty($idea['title']) || empty($idea['problem']) || empty($idea['solution'])) {
                continue;
            }
            try {
                ResearchIdea::create([
                    'window_date' => $window,
                    'title' => mb_substr((string) $idea['title'], 0, 255),
                    'target_users' => (string) ($idea['target_users'] ?? ''),
                    'problem' => (string) $idea['problem'],
                    'solution' => (string) $idea['solution'],
                    'sources' => $sources,
                ]);
                $created++;
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // dup judul di window sama — skip
            }
        }

        return $created;
    }

    private function finish(ResearchAgentSettings $settings, string $status, int $created): array
    {
        $settings->forceFill(['last_run_at' => Carbon::now(), 'last_run_status' => mb_substr($status, 0, 255)])->save();

        return ['status' => $status, 'created' => $created];
    }
}
