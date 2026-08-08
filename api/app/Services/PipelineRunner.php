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

    private const ALL_STAGES = [
        'pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract',
        'phases_web', 'standards_web', 'master_web',
        'pertanyaan_mobile',
        'phases_mobile', 'standards_mobile', 'master_mobile',
        'agents',
    ];

    // Stage mana yang termasuk jalur mobile (hanya untuk target 'both').
    private const MOBILE_STAGES = ['pertanyaan_mobile', 'phases_mobile', 'standards_mobile', 'master_mobile'];

    // Stage sebelum mobile track → gate: mobile menunggu web selesai.
    private const WEB_DONE_STAGE = 'master_web';

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

    public function run(?string $stage, bool $auto): void
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
            $target = $this->version->project->target ?? 'web';

            // Stage jalur mobile HANYA untuk target 'both', dan menunggu web selesai.
            if (in_array($key, self::MOBILE_STAGES, true)) {
                if ($target !== 'both') {
                    $this->updateStageStatus($key, 'done');

                    continue;
                }
                // Gate: mobile tidak boleh mulai sebelum master_web done.
                if (($this->version->stage_status[self::WEB_DONE_STAGE] ?? 'pending') !== 'done') {
                    $this->emit('status', ['stage' => 'web_gate', 'state' => 'waiting']);
                    $this->emit('fail', ['stage' => $key, 'message' => 'Web track belum selesai. Selesaikan master_web sebelum melanjutkan ke mobile.']);

                    return;
                }
            }

            $this->emit('status', ['stage' => $key, 'state' => 'running']);
            $this->updateStageStatus($key, 'running');

            try {
                $content = $this->runStage($key);
                if ($key === 'pertanyaan') {
                    $content = $this->retryPertanyaanForMinimum($content);
                }
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
            if (! $locked) {
                return;
            }

            $status = array_merge($locked->stage_status ?? [], [$stage => $state]);
            $locked->update(['stage_status' => $status]);
            $this->version = $locked->fresh();
        });
    }

    private function runStage(string $key, ?string $overrideTarget = null, string $extraInstruction = ''): string
    {
        $messages = $this->buildMessages($key, $overrideTarget);
        $system = $messages[0]['content'] ?? '';
        if ($extraInstruction !== '' && is_string($system)) {
            $messages[0]['content'] = $system."\n\n[PERINGATAN TAMBAHAN]\n{$extraInstruction}";
        }
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
        // Stage jalur mobile selalu menargetkan platform mobile.
        if (in_array($stage, self::MOBILE_STAGES, true)) {
            $target = 'mobile';
            $overrideTarget = 'mobile';
        } elseif (in_array($stage, ['phases_web', 'standards_web', 'master_web'], true)) {
            // Track web selalu diselesaikan sebagai platform web (walau target both).
            $target = 'web';
            $overrideTarget = 'web';
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
        $helpers = __DIR__.'/../Prompts/helpers.php';
        if (file_exists($helpers)) {
            require_once $helpers;
        }

        $promptStage = match ($stage) {
            'standards_web' => 'standards',
            'standards_mobile' => 'standards',
            'phases_web' => 'phases',
            'master_web' => 'phased_master',
            'phases_mobile' => 'phases_mobile',
            'master_mobile' => 'phased_master_mobile',
            'pertanyaan_mobile' => 'pertanyaan_mobile',
            default => $stage,
        };
        $path = __DIR__."/../Prompts/{$promptStage}.php";
        if (! file_exists($path)) {
            return '';
        }

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
        if (! empty($answers)) {
            $answersText = '';
            foreach ($answers as $q => $a) {
                $answersText .= "- {$q}: {$a}\n";
            }
            $ctx .= "\n\n### Jawaban Klarifikasi\n{$answersText}";
        }

        return match ($stage) {
            'pertanyaan' => $ctx,

            'analisa' => $ctx,

            'prd' => $ctx."\n\n### Hasil Analisa\n{$v->analysis}\n\n### Ide Awal\n{$idea}\n### Target Platform\n{$target}",

            'architecture' => $ctx."\n\n### Dokumen PRD\n{$v->prd}",

            'erd' => $ctx."\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur\n{$v->architecture}",

            'standards_web' => $ctx."\n\n### Analisa\n{$v->analysis}\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur\n{$v->architecture}\n\n### ERD & API Contract\n".json_encode($v->erd ?? new \stdClass, JSON_PRETTY_PRINT),

            'phases_web' => $ctx."\n\n### Standars\n{$v->standards}\n\n### AGENTS\n{$v->agents}\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur\n{$v->architecture}\n\n### ERD & API Contract\n".json_encode($v->erd ?? new \stdClass, JSON_PRETTY_PRINT)."\n\n### Version ID\n{$v->id}\n### Webhook URL (untuk tracking phase)\n".config('app.url').'/api/webhooks/phase-complete',

            'master_web' => $ctx."\n\n### Standars (web)\n{$v->standards}\n\n### AGENTS (web)\n{$v->agents}\n\n### Analisa\n{$v->analysis}\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur\n{$v->architecture}\n\n### ERD & API Contract\n".json_encode($v->erd ?? ['nodes'=>[],'edges'=>[],'api_contract'=>[]], JSON_PRETTY_PRINT)."\n\n### Version ID\n{$v->id}\n### Webhook URL (untuk tracking phase)\n".config('app.url').'/api/webhooks/phase-complete',

            'pertanyaan_mobile' => $ctx."\n\n### Master Prompt Web (SUDAH SELESAI)\n{$v->master_prompt}\n\n### API Contract\n".json_encode($v->erd ? ($v->erd['api_contract'] ?? []) : [], JSON_PRETTY_PRINT)."\n\n### ERD\n".json_encode($v->erd ?? ['nodes'=>[],'edges'=>[]], JSON_PRETTY_PRINT),

            'phases_mobile' => $ctx."\n\n### Mobile Answers (klarifikasi mobile)\n".($v->mobile_answers ? json_encode($v->mobile_answers, JSON_PRETTY_PRINT) : '_Belum ada_')."\n\n### Standars Mobile\n{$v->mobile_standards}\n\n### Dokumen PRD (web)\n{$v->prd}\n\n### Arsitektur (web)\n{$v->architecture}\n\n### ERD & API Contract\n".json_encode($v->erd ?? ['nodes'=>[],'edges'=>[],'api_contract'=>[]], JSON_PRETTY_PRINT)."\n\n### Master Prompt Web (SUDAH SELESAI — referensi lengkap web)\n{$v->master_prompt}\n\n### Version ID\n{$v->id}\n### Webhook URL (untuk tracking phase)\n".config('app.url').'/api/webhooks/phase-complete',

            'standards_mobile' => $ctx."\n\n### Mobile Answers\n".($v->mobile_answers ? json_encode($v->mobile_answers, JSON_PRETTY_PRINT) : '_Belum ada_')."\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur (web)\n{$v->architecture}\n\n### ERD & API Contract\n".json_encode($v->erd ?? ['nodes'=>[],'edges'=>[],'api_contract'=>[]], JSON_PRETTY_PRINT)."\n\n### Master Web (SUDAH SELESAI)\n{$v->master_prompt}",

            'master_mobile' => $ctx."\n\n### Mobile Answers\n".($v->mobile_answers ? json_encode($v->mobile_answers, JSON_PRETTY_PRINT) : '_Belum ada_')."\n\n### Standars Mobile\n{$v->mobile_standards}\n\n### AGENTS Mobile\n{$v->mobile_agents}\n\n### Analisa\n{$v->analysis}\n\n### Dokumen PRD\n{$v->prd}\n\n### Dokumen Arsitektur (web)\n{$v->architecture}\n\n### ERD & API Contract\n".json_encode($v->erd ?? ['nodes'=>[],'edges'=>[],'api_contract'=>[]], JSON_PRETTY_PRINT)."\n\n### Master Prompt Web (SUDAH 100% — referensi lengkap web)\n{$v->master_prompt}\n\n### Webhook URL (untuk tracking phase)\n".config('app.url').'/api/webhooks/phase-complete',

            'agents' => $ctx."\n\n### Master Prompt Web (WAJIB — base untuk semua agent)\n{$v->master_prompt}\n\n### Master Prompt Mobile (jika target=both, SUDAH SELESAI)\n".(($target === 'both' && ! empty($v->mobile_master_prompt)) ? $v->mobile_master_prompt : '_Belum ada (target=web)_'),

            default => $idea,
        };
    }

    private function saveArtifact(string $key, string $content): void
    {
        $map = [
            'pertanyaan' => 'pertanyaan',
            'pertanyaan_mobile' => 'pertanyaan_mobile',
            'analisa' => 'analysis',
            'prd' => 'prd',
            'architecture' => 'architecture',
            'erd' => 'erd',
            'api_contract' => 'api_contract',
            'phases_web' => 'phases',
            'standards_web' => 'standards',
            'master_web' => 'master_prompt',
            'phases_mobile' => 'mobile_phases',
            'standards_mobile' => 'mobile_standards',
            'master_mobile' => 'mobile_master_prompt',
            'agents' => 'agents',
        ];

        $col = $map[$key] ?? null;
        if (! $col) {
            return;
        }

        $value = $content;
        if ($key === 'architecture') {
            // Architecture disimpan sebagai TEXT mentah (bukan diagram).
            // parseArchText hanya opsional untuk validasi diagram; bila output
            // tidak berupa diagram, tetap simpan sebagai teks (jangan gagal).
            $this->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'pertanyaan' || $key === 'pertanyaan_mobile') {
            // Simpan & emit sebagai JSON bersih bila output AI valid (hindari
            // flash fallback di frontend akibat prose/fence/trailing comma).
            $cleaned = $this->extractJson($content);
            $decoded = $this->tryJsonDecode($cleaned);
            if ($decoded !== null) {
                $value = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                $this->emit('artifact', ['stage' => $key, 'content' => $value]);
            } else {
                $this->emit('artifact', ['stage' => $key, 'content' => $content]);
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
                throw new \RuntimeException('ERD: Gagal parse output AI. Stage ditandai error.');
            }
        } elseif ($key === 'phases_web' || $key === 'phases_mobile') {
            $phases = $this->parsePhasesText($content);
            if ($phases === null) {
                throw new \RuntimeException('Phases: Gagal parse output AI. Stage ditandai error.');
            }
            $this->version->update([$col => $phases]);
            $value = $content;
            $this->emit('artifact', ['stage' => $key, 'content' => $content]);
        } elseif ($key === 'api_contract') {
            $cleaned = $this->extractJson($content);
            $decoded = $this->tryJsonDecode($cleaned);
            if ($decoded !== null) {
                // Normalisasi ke bentuk ARRAY endpoint — konsisten dengan
                // api_contract hasil stage ERD & renderer frontend.
                // Dukungan dua format:
                //   1) [ {method,path,description,auth}, ... ]   (langsung)
                //   2) { endpoints: [...], base_url, auth, ... } (objek pembungkus)
                if (isset($decoded['endpoints']) && is_array($decoded['endpoints']) && $this->isListKey($decoded, 'endpoints')) {
                    $value = $decoded['endpoints'];
                } elseif ($this->isEndpointList($decoded)) {
                    $value = $decoded;
                } else {
                    throw new \RuntimeException("API Contract: struktur tidak dikenali. Stage ditandai error.");
                }
                $this->emit('artifact', ['stage' => $key, 'content' => json_encode($value, JSON_PRETTY_PRINT)]);
            } else {
                throw new \RuntimeException("JSON tidak valid untuk stage {$key}. Stage ditandai error.");
            }
        } else {
            $this->emit('artifact', ['stage' => $key, 'content' => $content]);
        }

        $this->version->update([$col => $value]);
    }

    private const MIN_MCQ_QUESTIONS = 5;

    private const MAX_MCQ_RETRIES = 4;

    private function retryPertanyaanForMinimum(string $content): string
    {
        if ($this->mcqCount($content) >= self::MIN_MCQ_QUESTIONS) {
            return $content;
        }

        $prompt = $this->contextPrompt('pertanyaan', $this->version);
        for ($i = 1; $i <= self::MAX_MCQ_RETRIES; $i++) {
            $this->emit('status', ['stage' => 'pertanyaan', 'state' => 'retrying', 'attempt' => $i, 'message' => "Pertanyaan kurang dari ".self::MIN_MCQ_QUESTIONS.', generate ulang...']);
            $content = $this->runStage('pertanyaan', null, 'Kamu sebelumnya mengeluarkan kurang dari '.self::MIN_MCQ_QUESTIONS.' pertanyaan. Output ulang SELURUH JSON dengan minimal '.self::MIN_MCQ_QUESTIONS.' pertanyaan (target 5-10) berdasarkan konteks ini: '.$prompt);
            if ($this->mcqCount($content) >= self::MIN_MCQ_QUESTIONS) {
                break;
            }
        }
        $this->emit('status', ['stage' => 'pertanyaan', 'state' => 'running']);

        return $content;
    }

    private function mcqCount(string $content): int
    {
        $cleaned = $this->extractJson($content);
        $decoded = $this->tryJsonDecode($cleaned);
        if (! is_array($decoded)) {
            return 0;
        }
        $questions = $decoded['questions'] ?? [];

        return is_array($questions) ? count($questions) : 0;
    }

    private function isListKey(array $arr, string $key): bool
    {
        return array_is_list($arr[$key]);
    }

    private function isEndpointList(array $arr): bool
    {
        if (! array_is_list($arr)) {
            return false;
        }
        foreach ($arr as $item) {
            if (! is_array($item)) {
                return false;
            }
            if (! isset($item['method'], $item['path'])) {
                return false;
            }
        }

        return true;
    }

    private function parseErdText(string $content): ?array
    {
        $nodes = [];
        $edges = [];
        $api = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

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

        // Fallback: bila output AI berupa JSON block (bukan baris format),
        // ambil nodes/edges/api_contract dari sana. Lengkapi bagian yang kosong.
        $json = $this->parseJsonErd($content);
        if ($json !== null) {
            if (empty($nodes)) {
                $nodes = $json['nodes'];
                $edges = array_merge($edges, $json['edges']);
            }
            if (empty($api)) {
                $api = array_merge($api, $json['api_contract']);
            }
        }

        if (empty($nodes)) {
            return null;
        }

        return ['nodes' => $nodes, 'edges' => $edges, 'api_contract' => $api];
    }

    private function parseJsonErd(string $content): ?array
    {
        $cleaned = $this->extractJson($content);
        if ($cleaned === '') {
            return null;
        }

        $decoded = $this->tryJsonDecode($cleaned);
        if (! is_array($decoded)) {
            return null;
        }

        $nodes = $decoded['nodes'] ?? [];
        $edges = $decoded['edges'] ?? [];
        $api = $decoded['api_contract'] ?? $decoded['apiContract'] ?? [];

        $nodes = is_array($nodes) ? array_values($nodes) : [];
        $edges = is_array($edges) ? array_values($edges) : [];
        $api = is_array($api) ? array_values($api) : [];

        if (empty($nodes) && empty($edges) && empty($api)) {
            return null;
        }

        return ['nodes' => $nodes, 'edges' => $edges, 'api_contract' => $api];
    }

    private function parseArchText(string $content): ?array
    {
        $nodes = [];
        $edges = [];

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

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

        if (empty($nodes)) {
            return null;
        }

        return ['nodes' => $nodes, 'edges' => $edges];
    }

    private function parsePhasesText(string $content): ?array
    {
        $phases = [];
        $blocks = preg_split('/^-{3,}\s*$/m', $content);

        foreach ($blocks as $block) {
            $block = trim($block);
            if ($block === '') {
                continue;
            }

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
                    if ($prompt !== '' && ! preg_match('/^(FASE|TASK|PROMPT):/i', $line)) {
                        $prompt .= "\n".$line;
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

        return ! empty($phases) ? $phases : null;
    }

    private function extractJson(string $content): string
    {
        // BOM & whitespace
        $content = trim(preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? '');
        if ($content === '') {
            return '';
        }

        // Strip ALL fenced code blocks (```json ... ```, ``` ... ```, ```JSON ... ```),
        // regardless of surrounding prose or how many blocks.
        $content = (string) preg_replace('/```(?:json)?\s*\n?(.*?)```/si', '$1', $content);
        $content = trim($content);

        $openBrace = strpos($content, '{');
        $openBracket = strpos($content, '[');
        if ($openBrace === false && $openBracket === false) {
            return '';
        }
        // Pilih bracket yang muncul paling awal — menangkap struktur atas (object ATAU array).
        $open = ($openBrace !== false && ($openBracket === false || $openBrace < $openBracket))
            ? $openBrace
            : $openBracket;

        // Extract from first { or [ to its matching closing bracket,
        // skipping string literals so nested } ] inside strings are safe.
        $closer = $content[$open] === '{' ? '}' : ']';
        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($content);
        $end = -1;

        for ($i = $open; $i < $length; $i++) {
            $ch = $content[$i];
            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                } elseif ($ch === '\\') {
                    $escaped = true;
                } elseif ($ch === '"') {
                    $inString = false;
                }
                continue;
            }
            if ($ch === '"') {
                $inString = true;
                continue;
            }
            if ($ch === '{' || $ch === '[') {
                $depth++;
            } elseif ($ch === '}' || $ch === ']') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }

        if ($end === -1) {
            $end = strrpos($content, $closer);
            if ($end === false || $end <= $open) {
                return '';
            }
        }

        $sub = substr($content, $open, $end - $open + 1);
        // Remove trailing commas before ] or }
        $sub = (string) preg_replace('/,\s*([\]}])/', '$1', $sub);

        return trim($sub);
    }

    private function tryJsonDecode(string $content): ?array
    {
        // Strategy 1: Direct parse
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Strategy 2: Strip control characters
        $stripped = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $content);
        $decoded = json_decode($stripped, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Strategy 3: Quote unquoted object keys (common LLM flaw)
        $quotedKeys = (string) preg_replace(
            '/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)(\s*:)/',
            '$1"$2"$3',
            $stripped
        );
        $decoded = json_decode($quotedKeys, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Strategy 4: Balance brackets — append missing closers progressively
        foreach (['[', '{'] as $open) {
            $close = $open === '[' ? ']' : '}';
            $missing = substr_count($stripped, $open) - substr_count($stripped, $close);
            if ($missing > 0 && $missing < 30) {
                $candidate = $stripped.str_repeat($close, $missing);
                $decoded = json_decode($candidate, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    return $decoded;
                }
            }
        }

        // Strategy 5: Trim trailing annotations progressively (larger window)
        for ($i = 0; $i < 40; $i++) {
            $trimmed = substr($stripped, 0, -1 - $i);
            $decoded = json_decode($trimmed, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        // Strategy 6a: single-quoted values + quoted keys combined
        $singleQuoted = (string) preg_replace("/'([^']+)'/", '"$1"', $stripped);
        $combo = (string) preg_replace(
            '/([{,]\s*)([A-Za-z_][A-Za-z0-9_]*)(\s*:)/',
            '$1"$2"$3',
            $singleQuoted
        );
        $decoded = json_decode($combo, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Strategy 7: single-quoted JSON (fallback, can corrupt real apostrophes)
        $decoded = json_decode($singleQuoted, true, 512, JSON_INVALID_UTF8_SUBSTITUTE);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

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
