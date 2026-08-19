<?php

namespace App\Services;

class AiOutputParser
{
    public function __construct(
        private readonly AiJsonParser $jsonParser = new AiJsonParser,
    ) {}

    /**
     * Extract all Markdown headings (## and ### level).
     * Returns list of heading text including the marker, e.g. ['## 0. Pin the Subject', '### Screen 1: Login'].
     */
    public function extractMarkdownHeadings(string $content): array
    {
        $headings = [];
        if (preg_match_all('/^(#{1,4})\s+(.+)$/m', $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $m) {
                $headings[] = $m[1].' '.$m[2];
            }
        }

        return $headings;
    }

    /**
     * Extract body of first code fence with given language (e.g. 'css', 'dart', 'php').
     * Returns null if not found.
     */
    public function extractCodeFence(string $content, string $lang): ?string
    {
        $pattern = '/```'.preg_quote($lang, '/').'\s*\n?(.*?)```/si';
        if (preg_match($pattern, $content, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * Extract variable names from .env.example block (KEY=value or KEY=...).
     * Returns list of uppercase snake_case keys.
     */
    /**
     * W4 — Extract code fence tolerating language suffix variants (e.g. ```php8, ```env.example).
     */
    public function extractCodeFencePrefix(string $content, string $prefix): ?string
    {
        $pattern = '/```'.preg_quote($prefix, '/').'[\w.+#-]*\s*\n?(.*?)```/si';
        if (preg_match($pattern, $content, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    public function extractEnvVars(string $envExampleBlock): array
    {
        $vars = [];
        if (preg_match_all('/^\s*([A-Z][A-Z0-9_]*)\s*=/m', $envExampleBlock, $matches)) {
            foreach ($matches[1] as $v) {
                $vars[] = $v;
            }
        }

        return array_values(array_unique($vars));
    }

    /**
     * Count checklist items (- [ ] or - [x]) in content.
     */
    public function extractChecklistItems(string $content): int
    {
        $count = preg_match_all('/^\s*-\s*\[[ x]\]/m', $content);
        if ($count === false) {
            return 0;
        }

        return $count;
    }

    /**
     * Parse and validate app_spec JSON artifact.
     * Returns array with keys: data (validated spec) | null, errors (array of string).
     */
    public function parseAppSpecJson(string $content, string $platform = 'web'): array
    {
        $cleaned = $this->jsonParser->extractJson($content);
        $decoded = $this->jsonParser->tryJsonDecode($cleaned);
        if (! is_array($decoded)) {
            return ['data' => null, 'errors' => ['JSON tidak valid atau tidak dapat di-parse.']];
        }

        $errors = [];
        $halamanKey = $platform === 'mobile' ? 'screens' : 'halaman';

        // Required top-level keys
        $halamanKey = $platform === 'mobile' ? 'screens' : 'halaman';
        $componentsKey = $platform === 'mobile' ? 'widgets' : 'components';
        foreach (['version', 'generated_from_stages', $halamanKey, 'navigation', 'flows', $componentsKey] as $key) {
            if (! isset($decoded[$key])) {
                $errors[] = "Field wajib '{$key}' tidak ada.";
            }
        }

        if (! empty($errors)) {
            return ['data' => null, 'errors' => $errors];
        }

        // Validate halaman count
        if (! is_array($decoded[$halamanKey]) || count($decoded[$halamanKey]) < 3) {
            $errors[] = "Field '{$halamanKey}' WAJIB array dengan minimal 3 entry.";
        }

        // Validate navigation
        if (! is_array($decoded['navigation']) || empty($decoded['navigation']['primary_menu']) || count($decoded['navigation']['primary_menu']) < 2) {
            $errors[] = "Field 'navigation.primary_menu' WAJIB array dengan minimal 2 entry.";
        }

        // Validate flows
        if (! is_array($decoded['flows']) || count($decoded['flows']) < 1) {
            $errors[] = "Field 'flows' WAJIB array dengan minimal 1 entry.";
        } else {
            foreach ($decoded['flows'] as $i => $flow) {
                if (! is_array($flow) || empty($flow['steps']) || count($flow['steps']) < 2) {
                    $errors[] = "Flow #{$i} WAJIB punya minimal 2 steps.";
                }
            }
        }

        // Validate components (or widgets for mobile)
        if (! is_array($decoded[$componentsKey]) || count($decoded[$componentsKey]) < 3) {
            $errors[] = "Field '{$componentsKey}' WAJIB array dengan minimal 3 entry.";
        }

        // Cross-reference checks
        if (is_array($decoded[$halamanKey]) && is_array($decoded[$componentsKey])) {
            $componentKeys = array_column($decoded[$componentsKey], 'key');
            $halamanKeys = array_column($decoded[$halamanKey], 'key');

            foreach ($decoded[$halamanKey] as $i => $h) {
                if (! is_array($h)) {
                    continue;
                }
                if (empty($h['key']) || empty($h['title'])) {
                    $errors[] = ucfirst($halamanKey)." #{$i} WAJIB punya 'key' dan 'title'.";
                }
                $usedKey = $platform === 'mobile' ? 'widgets_used' : 'components_used';
                if (isset($h[$usedKey]) && is_array($h[$usedKey])) {
                    foreach ($h[$usedKey] as $ck) {
                        if (! in_array($ck, $componentKeys, true)) {
                            $errors[] = ucfirst($halamanKey)." '{$h['key']}' reference {$componentsKey} '{$ck}' yang tidak ada di registry.";
                        }
                    }
                }
                if ($platform === 'web' && isset($h['route']) && $h['route'] !== '' && ! str_starts_with((string) $h['route'], '/')) {
                    $errors[] = ucfirst($halamanKey)." '{$h['key']}' route WAJIB dimulai dengan '/'.";
                }
            }

            foreach ($decoded[$componentsKey] as $i => $c) {
                if (! is_array($c)) {
                    continue;
                }
                if (empty($c['key'])) {
                    $errors[] = ucfirst($componentsKey)." #{$i} WAJIB punya 'key'.";
                }
                if (isset($c['used_in']) && is_array($c['used_in'])) {
                    foreach ($c['used_in'] as $hk) {
                        if (! in_array($hk, $halamanKeys, true)) {
                            $errors[] = ucfirst($componentsKey)." '{$c['key']}' used_in '{$hk}' yang tidak ada di ".($platform === 'mobile' ? 'screens' : 'halaman').' registry.';
                        }
                    }
                }
            }

            // Validate flow steps reference existing halaman
            foreach ($decoded['flows'] as $fi => $flow) {
                if (! is_array($flow) || ! is_array($flow['steps'] ?? null)) {
                    continue;
                }
                foreach ($flow['steps'] as $si => $step) {
                    if (! is_array($step)) {
                        continue;
                    }
                    foreach (['from', 'to'] as $field) {
                        if (isset($step[$field]) && ! in_array($step[$field], $halamanKeys, true)) {
                            $errors[] = "Flow '{$flow['key']}' step #{$si} {$field} '{$step[$field]}' tidak ada di halaman registry.";
                        }
                    }
                }
            }
        }

        if (! empty($errors)) {
            return ['data' => null, 'errors' => $errors];
        }

        return ['data' => $decoded, 'errors' => []];
    }

    public function parseErdText(string $content): ?array
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

        $json = $this->parseJsonErd($content);
        if ($json !== null) {
            // Prefer JSON wholesale when no text-parsed nodes exist — avoid
            // dangling edges from text mode referencing IDs not in JSON nodes.
            if (empty($nodes)) {
                $nodes = $json['nodes'];
                $edges = $json['edges'];
            }
            if (empty($api)) {
                $api = $json['api_contract'];
            }
        }

        if (empty($nodes)) {
            return null;
        }

        return ['nodes' => $nodes, 'edges' => $edges, 'api_contract' => $api];
    }

    public function parseJsonErd(string $content): ?array
    {
        $cleaned = $this->jsonParser->extractJson($content);
        if ($cleaned === '') {
            return null;
        }

        $decoded = $this->jsonParser->tryJsonDecode($cleaned);
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

    public function parsePhasesText(string $content): ?array
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
            $halaman = [];
            $menu = [];
            $fitur = [];
            $flow = [];
            $api = [];

            foreach (explode("\n", $block) as $line) {
                $line = trim($line);
                if (preg_match('/^FASE:\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                    $key = trim($m[1]);
                    $title = trim($m[2]);
                } elseif (preg_match('/^TASK:\s*(.+)$/i', $line, $m)) {
                    $tasks[] = trim($m[1]);
                } elseif (preg_match('/^HALAMAN:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                    $halaman[] = ['key' => trim($m[1]), 'title' => trim($m[2]), 'desc' => trim($m[3])];
                } elseif (preg_match('/^MENU:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                    $menu[] = ['key' => trim($m[1]), 'title' => trim($m[2]), 'parent' => trim($m[3])];
                } elseif (preg_match('/^FITUR:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                    $fitur[] = ['key' => trim($m[1]), 'title' => trim($m[2]), 'func' => trim($m[3])];
                } elseif (preg_match('/^FLOW:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                    $flow[] = ['key' => trim($m[1]), 'title' => trim($m[2]), 'steps' => trim($m[3])];
                } elseif (preg_match('/^API:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i', $line, $m)) {
                    $api[] = ['key' => trim($m[1]), 'endpoint' => trim($m[2]), 'method' => trim($m[3]), 'desc' => trim($m[4])];
                } elseif (preg_match('/^PROMPT:\s*(.+)$/i', $line, $m)) {
                    $prompt = trim($m[1]);
                } else {
                    if ($prompt !== '' && ! preg_match('/^(FASE|TASK|PROMPT|HALAMAN|MENU|FITUR|FLOW|API):/i', $line)) {
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
                    'halaman' => $halaman,
                    'menu' => $menu,
                    'fitur' => $fitur,
                    'flow' => $flow,
                    'api' => $api,
                ];
            }
        }

        return ! empty($phases) ? $phases : null;
    }

    public function isEndpointList(array $arr): bool
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

    public function isListKey(array $arr, string $key): bool
    {
        return array_is_list($arr[$key]);
    }

    public function mcqCount(string $content): int
    {
        $cleaned = $this->jsonParser->extractJson($content);
        $decoded = $this->jsonParser->tryJsonDecode($cleaned);
        if (is_array($decoded)) {
            $questions = $decoded['questions'] ?? $this->findQuestionsNested($decoded);
            if (is_array($questions) && count($questions) > 0) {
                return $this->mcqValidCount($content);
            }
        }

        // Fallback teks: AI kadang output numbered list / baris bertanda "?" (bukan JSON).
        // Frontend punya fallback render teks, jadi konten ini tetap berguna.
        return $this->mcqCountText($content);
    }

    /**
     * Hitung HANYA pertanyaan VALID (struktur lengkap), bukan count mentah array.
     * Kriteria valid: id string non-kosong, question string non-kosong,
     * options array >= 4 dengan tiap option punya key + text string non-kosong.
     */
    public function mcqValidCount(string $content): int
    {
        $cleaned = $this->jsonParser->extractJson($content);
        $decoded = $this->jsonParser->tryJsonDecode($cleaned);
        if (! is_array($decoded)) {
            return 0;
        }
        $questions = $decoded['questions'] ?? $this->findQuestionsNested($decoded);
        if (! is_array($questions)) {
            return 0;
        }

        $valid = 0;
        foreach ($questions as $q) {
            if (! is_array($q)) {
                continue;
            }
            $id = $q['id'] ?? null;
            $question = $q['question'] ?? null;
            $options = $q['options'] ?? null;
            if (! is_string($id) || trim($id) === '') {
                continue;
            }
            if (! is_string($question) || trim($question) === '') {
                continue;
            }
            if (! is_array($options) || count($options) < 4) {
                continue;
            }
            $optsOk = true;
            foreach ($options as $opt) {
                if (! is_array($opt) || ! is_string($opt['key'] ?? null) || trim($opt['key'] ?? '') === '' || ! is_string($opt['text'] ?? null) || trim($opt['text'] ?? '') === '') {
                    $optsOk = false;
                    break;
                }
            }
            if ($optsOk) {
                $valid++;
            }
        }

        return $valid;
    }

    /** Cari array 'questions' di wrapper umum: response/questions, data/questions, top-level. */
    private function findQuestionsNested(array $decoded): mixed
    {
        foreach (['response', 'data', 'result', 'payload'] as $key) {
            if (isset($decoded[$key]) && is_array($decoded[$key])) {
                if (isset($decoded[$key]['questions']) && is_array($decoded[$key]['questions'])) {
                    return $decoded[$key]['questions'];
                }
                $found = $this->findQuestionsNested($decoded[$key]);
                if (is_array($found)) {
                    return $found;
                }
            }
        }

        return null;
    }

    /**
     * Hitung pertanyaan dari teks: numbered list ("1. ...", "1) ...") atau baris berakhiran "?".
     * Contoh: "1. Fitur apa yang paling penting?" → 1. Bilang prosa tanpa pola tidak dihitung.
     */
    public function mcqCountText(string $content): int
    {
        $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];
        $count = 0;
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $numbered = (bool) preg_match('/^\d+[.)]\s*\S/', $line);
            $question = (bool) preg_match('/^[#*>\-\s]*\S.*\?\s*$/', $line);
            if ($numbered || $question) {
                $count++;
            }
        }

        return $count;
    }
}
