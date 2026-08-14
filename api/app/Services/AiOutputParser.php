<?php

namespace App\Services;

class AiOutputParser
{
    public function __construct(
        private readonly AiJsonParser $jsonParser = new AiJsonParser,
    ) {}

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
        if (! is_array($decoded)) {
            return 0;
        }
        $questions = $decoded['questions'] ?? [];

        return is_array($questions) ? count($questions) : 0;
    }
}
