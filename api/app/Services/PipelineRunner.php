<?php

namespace App\Services;

use App\Models\Version;
use Illuminate\Support\Facades\DB;
use Throwable;

class PipelineRunner
{
    private Version $version;
    private AiClient $client;
    /** @var resource */
    private $stdout;

    private const ALL_STAGES = ['pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'phased_master', 'phased_master_mobile'];

    public function __construct(Version $version, AiClient $client, $stdout = null)
    {
        $this->version = $version;
        $this->client = $client;
        $this->stdout = $stdout ?? fopen('php://output', 'w');
    }

    public function __destruct()
    {
        if (is_resource($this->stdout)) {
            fclose($this->stdout);
        }
    }

    public function run(string|null $stage, bool $auto): void
    {
        // Eager load project to avoid N+1 on every stage iteration
        $this->version->load('project');

        if (! $this->client->isConfigured()) {
            $this->emit('fail', ['stage' => $stage ?? 'start', 'message' => 'AI Provider belum dikonfigurasi.']);
            return;
        }

        $startIdx = $stage !== null
            ? array_search($stage, self::ALL_STAGES, true)
            : 0;

        if ($startIdx === false) {
            $startIdx = 0;
        }

        foreach (array_slice(self::ALL_STAGES, $startIdx) as $key) {
            // Only run phased_master_mobile for 'both' target
            if ($key === 'phased_master_mobile' && ($this->version->project->target ?? 'web') !== 'both') {
                $this->updateStageStatus($key, 'done');
                continue;
            }

            $this->emit('status', ['stage' => $key, 'state' => 'running']);
            $this->updateStageStatus($key, 'running');

            try {
                $content = $this->runStage($key);
                $this->saveArtifact($key, $content);

                $this->emit('done', ['stage' => $key]);
                $this->emit('status', ['stage' => $key, 'state' => 'done']);
                $this->updateStageStatus($key, 'done');
            } catch (Throwable $e) {
                $this->emit('fail', ['stage' => $key, 'message' => $e->getMessage()]);
                $this->updateStageStatus($key, 'error');
                if (! $auto) {
                    return;
                }
            }

            if (! $auto) {
                return;
            }
        }
    }

    private function updateStageStatus(string $stage, string $state): void
    {
        DB::transaction(function () use ($stage, $state) {
            $locked = Version::where('id', $this->version->id)->lockForUpdate()->first();
            if (! $locked) return;

            $status = array_merge($locked->stage_status ?? [], [$stage => $state]);
            $locked->update(['stage_status' => $status]);
            $this->version = $locked->fresh();
        });
    }

    private function runStage(string $key, ?string $overrideTarget = null): string
    {
        $messages = $this->buildMessages($key, $overrideTarget);
        $buffer = '';
        $maxChunks = 3;
        $maxBufferBytes = 10 * 1024 * 1024; // 10MB limit

        for ($i = 0; $i < $maxChunks; $i++) {
            if (strlen($buffer) >= $maxBufferBytes) {
                throw new \RuntimeException("Stage {$key}: Output melebihi batas 10MB. Stage ditandai error.");
            }
            $prevLen = strlen($buffer);
            $this->client->stream($messages, function (string $delta) use (&$buffer, $key, $maxBufferBytes) {
                if (strlen($buffer) + strlen($delta) > $maxBufferBytes) {
                    $delta = mb_substr($delta, 0, $maxBufferBytes - strlen($buffer));
                    $buffer .= $delta;
                    $this->emit('token', ['stage' => $key, 'delta' => $delta]);
                    return;
                }
                $buffer .= $delta;
                $this->emit('token', ['stage' => $key, 'delta' => $delta]);
            });

            $added = strlen($buffer) - $prevLen;
            $trimmed = trim($buffer);
            $endsNatural = $added < 50 || preg_match('/[.\n}\]>!?]$/', $trimmed) || $trimmed === '';

            if ($this->client->lastFinishReason === 'stop' && $endsNatural) {
                break;
            }
            if ($this->client->lastFinishReason === 'length') {
                // Terpotong oleh token limit → lanjut continuation
            } elseif ($added < 20) {
                // Hampir tidak ada output baru → AI selesai
                break;
            }

            // Continuation: prompt AI to continue from where it stopped
            $last200 = mb_substr($buffer, -200);
            $messages = [
                ['role' => 'system', 'content' => 'Lanjutkan output dari bagian sebelumnya. JANGAN ulangi konten yang sudah ada. Langsung lanjutkan dari bagian terakhir yang terpotong.'],
                ['role' => 'user', 'content' => "Lanjutkan dari bagian terakhir:\n{$last200}\n\n---\nLanjutkan dari sini. Jangan ulangi konten sebelumnya."],
            ];
        }

        return $buffer;
    }

    private function buildMessages(string $stage, ?string $overrideTarget = null): array
    {
        $v = $this->version;
        $target = $overrideTarget ?? $v->project->target ?? 'web';
        // phased_master_mobile always targets mobile regardless of project target
        if ($stage === 'phased_master_mobile') {
            $target = 'mobile';
            $overrideTarget = 'mobile';
        }
        $system = $this->systemPrompt($stage, $target);
        $context = $this->contextPrompt($stage, $v, $overrideTarget);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $context],
        ];
    }

    private function systemPrompt(string $stage, string $target): string
    {
        $helpers = __DIR__ . '/../Prompts/helpers.php';
        if (file_exists($helpers)) require_once $helpers;

        $promptStage = $stage === 'phased_master_mobile' ? 'phased_master' : $stage;
        $path = __DIR__ . "/../Prompts/{$promptStage}.php";
        if (!file_exists($path)) return '';

        $loader = require $path;
        return $loader($target);
    }

    private function contextPrompt(string $stage, Version $v, ?string $overrideTarget = null): string
    {
        $idea = $v->project->idea;
        $target = $overrideTarget ?? $v->project->target ?? 'web';
        $answers = $v->answers ?? [];

        $stack = trim((string) ($v->project->stack ?? ''));
        if ($stack === '') {
            $stack = $this->techStackForTarget($target);
        }

        $ctx = "### Ide Aplikasi\n{$idea}\n\n### Target Platform\n{$target}\n\n### Tech Stack\n{$stack}";
        if (!empty($answers)) {
            $answersText = '';
            foreach ($answers as $q => $a) {
                $answersText .= "- {$q}: {$a}\n";
            }
            $ctx .= "\n\n### Jawaban Klarifikasi\n{$answersText}";
        }

        return match ($stage) {
            'pertanyaan' => $ctx,

            'analisa' => $ctx,

            'prd' => $ctx . "\n\n### Hasil Analisa\n{$v->analysis}\n\n### Ide Awal\n{$idea}\n### Target Platform\n{$target}",

            'architecture' => $ctx . "\n\n### Dokumen PRD\n{$v->prd}",

            'erd' => $ctx . "\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur\n{$v->architecture}",

            'phased_master' => $ctx . "\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur\n{$v->architecture}\n\n### ERD & API Contract\n" . json_encode($v->erd ?? new \stdClass(), JSON_PRETTY_PRINT) . "\n\n### Version ID\n{$v->id}\n### Webhook URL (untuk tracking phase)\n" . config('app.url') . "/api/webhooks/phase-complete",

            'phased_master_mobile' => $ctx . "\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur\n{$v->architecture}\n\n### ERD & API Contract\n" . json_encode($v->erd ?? new \stdClass(), JSON_PRETTY_PRINT) . "\n\n### Version ID\n{$v->id}\n### Webhook URL (untuk tracking phase)\n" . config('app.url') . "/api/webhooks/phase-complete",

            default => $idea,
        };
    }

    private function saveArtifact(string $key, string $content): void
    {
        $map = [
            'analisa' => 'analysis',
            'prd' => 'prd',
            'architecture' => 'architecture',
            'erd' => 'erd',
            'phased_master' => 'master_prompt',
            'phased_master_mobile' => 'mobile_master_prompt',
        ];

        $col = $map[$key] ?? null;
        if (! $col) {
            return;
        }

        $value = $content;
        if ($key === 'architecture') {
            $parsed = $this->parseArchText($content);
            if ($parsed !== null) {
                $this->emit('artifact', ['stage' => $key, 'content' => $content]);
            } else {
                throw new \RuntimeException("Arsitektur: Gagal parse output AI. Stage ditandai error.");
            }
        } elseif ($key === 'erd') {
            $parsed = $this->parseErdText($content);
            if ($parsed !== null) {
                $value = $parsed;
                if (isset($parsed['api_contract'])) {
                    $this->version->update(['api_contract' => $parsed['api_contract']]);
                }
                $this->emit('artifact', ['stage' => $key, 'content' => json_encode($parsed)]);
            } else {
                throw new \RuntimeException("ERD: Gagal parse output AI. Stage ditandai error.");
            }
        } elseif ($key === 'phased_master' || $key === 'phased_master_mobile') {
            $parsed = $this->parsePhasedMaster($content);
            if ($parsed !== null) {
                $update = [];
                if ($key === 'phased_master_mobile') {
                    $update['mobile_phases'] = $parsed['phases'];
                    $update['mobile_standards'] = $parsed['standards'];
                    $update['mobile_agents'] = $parsed['agents'];
                } else {
                    $update['phases'] = $parsed['phases'];
                    $update['standards'] = $parsed['standards'];
                    $update['agents'] = $parsed['agents'];
                }
                $this->version->update($update);
                $value = $parsed['master'];
                $this->emit('artifact', ['stage' => $key, 'content' => json_encode($parsed)]);
            } else {
                throw new \RuntimeException("Phased Master: Gagal parse output AI. Stage ditandai error.");
            }
        } elseif ($key === 'api_contract') {
            $cleaned = $this->extractJson($content);
            $decoded = $this->tryJsonDecode($cleaned);
            if ($decoded !== null) {
                $value = $decoded;
            } else {
                throw new \RuntimeException("JSON tidak valid untuk stage {$key}. Stage ditandai error.");
            }
        } else {
            $this->emit('artifact', ['stage' => $key, 'content' => $content]);
        }

        $this->version->update([$col => $value]);
    }

    private function parseErdText(string $content): ?array
    {
        $nodes = [];
        $edges = [];
        $api = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('/^TABEL:\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                $name = trim($m[1]);
                $fields = array_map('trim', explode(',', $m[2]));
                $nodes[] = ['id' => $name, 'label' => $name, 'fields' => $fields];
            } elseif (preg_match('/^RELASI:\s*(.+?)\s*->\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                $edges[] = ['from' => trim($m[1]), 'to' => trim($m[2]), 'relation' => trim($m[3])];
            } elseif (preg_match('/^API:\s*(\w+)\s*\|\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                $api[] = [
                    'method' => strtoupper(trim($m[1])),
                    'path' => trim($m[2]),
                    'description' => trim($m[3]),
                    'auth' => strtolower(trim($m[4])) === 'true' || trim($m[4]) === '1',
                ];
            }
        }

        if (empty($nodes)) return null;
        return ['nodes' => $nodes, 'edges' => $edges, 'api_contract' => $api];
    }

    private function parseArchText(string $content): ?array
    {
        $nodes = [];
        $edges = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') continue;

            if (preg_match('/^KOMPONEN:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                $id = trim($m[1]);
                $label = trim($m[2]);
                $fields = array_map('trim', explode(',', $m[3]));
                $nodes[] = ['id' => $id, 'label' => $label, 'fields' => $fields];
            } elseif (preg_match('/^KONEKSI:\s*(.+?)\s*->\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                $edges[] = [
                    'from' => trim($m[1]),
                    'to' => trim($m[2]),
                    'relation' => trim($m[3]),
                ];
            }
        }

        if (empty($nodes)) return null;
        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function parsePhasesText(string $content): ?array
    {
        $phases = [];
        $blocks = preg_split('/^-{3,}\s*$/m', $content);

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') continue;

            $key = '';
            $title = '';
            $tasks = [];
            $prompt = '';

            foreach (explode("\n", $block) as $line) {
                $line = trim($line);
                if (preg_match('/^FASE:\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                    $key = trim($m[1]);
                    $title = trim($m[2]);
                } elseif (preg_match('/^TASK:\s*(.+)$/i', $line, $m)) {
                    $tasks[] = trim($m[1]);
                } elseif (preg_match('/^PROMPT:\s*(.+)$/i', $line, $m)) {
                    $prompt = trim($m[1]);
                } else {
                    // Append continuation lines to the current PROMPT
                    if ($prompt !== '' && !preg_match('/^(FASE|TASK|PROMPT):/i', $line)) {
                        $prompt .= "\n" . $line;
                    }
                }
            }

            if ($key && $title) {
                $phases[] = [
                    'key' => $key,
                    'title' => $title,
                    'tasks' => $tasks,
                    'prompt' => $prompt,
                ];
            }
        }

        return !empty($phases) ? $phases : null;
    }

    private function parsePhasedMaster(string $content): ?array
    {
        $extract = function (string $marker, string $content): string {
            $pattern = '/^={3,}' . preg_quote($marker, '/') . '={3,}\s*$/m';
            $parts = preg_split($pattern, $content, 2);
            return trim($parts[1] ?? '');
        };

        $phasesText = $extract('PHASES', $content);
        $masterText = $extract('MASTER', $content);
        $standardsText = $extract('STANDARDS', $content);
        $agentsText = $extract('AGENTS', $content);

        $phases = $this->parsePhasesText($phasesText);

        return [
            'phases' => $phases ?? [],
            'master' => $masterText ?: $content,
            'standards' => $standardsText,
            'agents' => $agentsText,
        ];
    }

    private function extractJson(string $content): string
    {
        // Strip markdown code blocks: ```json ... ``` or ``` ... ```
        $content = preg_replace('/^```(?:json)?\s*\n?(.*?)\n?```$/s', '$1', $content);
        // Trim whitespace
        $content = trim($content);
        // Extract from first { to last }
        $first = strpos($content, '{');
        $last = strrpos($content, '}');
        if ($first !== false && $last !== false && $last > $first) {
            $content = substr($content, $first, $last - $first + 1);
        }
        // Remove trailing commas before ] or }
        $content = preg_replace('/,\s*([\]}])/', '$1', $content);
        return $content;
    }

    private function tryJsonDecode(string $content): ?array
    {
        // Strategy 1: Direct parse
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;

        // Strategy 2: Strip control characters
        $stripped = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
        $decoded = json_decode($stripped, true);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;

        // Strategy 3: Balance braces - try removing last chars one by one
        for ($i = 0; $i < 10; $i++) {
            $trimmed = substr($stripped, 0, -1 - $i);
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) return $decoded;
        }

        // Strategy 4: Fix missing [ after known keys + balance brackets
        $fixed = preg_replace('/"(nodes|edges|api_contract)":\s*(?!\[)/', '"$1": [', $stripped);
        $open = substr_count($fixed, '['); $close = substr_count($fixed, ']');
        while ($close < $open) { $fixed .= ']'; $close++; }
        $decoded = json_decode($fixed, true);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;

        // Strategy 5: Single quotes fix + brackets balanced
        $fixed = preg_replace("/'([^']+)'/", '"$1"', $stripped);
        $fixed = preg_replace('/"(nodes|edges|api_contract)":\s*(?!\[)/', '"$1": [', $fixed);
        $open = substr_count($fixed, '['); $close = substr_count($fixed, ']');
        while ($close < $open) { $fixed .= ']'; $close++; }
        $decoded = json_decode($fixed, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE) return $decoded;

        return null;
    }

    private function techStackForTarget(string $target): string
    {
        return match ($target) {
            'mobile' => 'Flutter + Dart + Riverpod + GoRouter + Material Design 3 + drift/sqflite',
            'both' => 'Web: Laravel 11 + Next.js + React 19 + Tailwind CSS v4 + PostgreSQL 16 | Mobile: Flutter + Dart + Riverpod + GoRouter + Material Design 3',
            default => 'Laravel 11 (PHP 8.4) + Next.js (App Router, React 19, TypeScript) + Tailwind CSS v4 + PostgreSQL 16',
        };
    }

    private function emit(string $event, array $data): void
    {
        $json = json_encode($data);
        if ($json === false) {
            $clean = [];
            foreach ($data as $k => $v) {
                $clean[$k] = is_string($v) ? mb_convert_encoding($v, 'UTF-8', 'UTF-8') : $v;
            }
            $json = json_encode($clean, JSON_INVALID_UTF8_SUBSTITUTE);
        }
        fwrite($this->stdout, "event: {$event}\ndata: {$json}\n\n");
        fwrite($this->stdout, ": ping\n\n");
        if (ob_get_level() > 0) {
            ob_flush();
        }
        if (function_exists('flush')) {
            flush();
        }
    }
}
