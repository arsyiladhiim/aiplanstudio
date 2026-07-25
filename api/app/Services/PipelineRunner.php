<?php

namespace App\Services;

use App\Models\Version;
use Throwable;

class PipelineRunner
{
    private Version $version;
    private AiClient $client;
    /** @var resource */
    private $stdout;

    private const ALL_STAGES = ['analisa', 'prd', 'architecture', 'erd', 'phases', 'master'];

    public function __construct(Version $version, AiClient $client)
    {
        $this->version = $version;
        $this->client = $client;
        $this->stdout = fopen('php://output', 'w');
    }

    public function run(string|null $stage, bool $auto): void
    {
        if (! $this->client->isConfigured()) {
            $this->emit('error', ['stage' => $stage ?? 'start', 'message' => 'AI Provider belum dikonfigurasi.']);
            return;
        }

        $startIdx = $stage !== null
            ? array_search($stage, self::ALL_STAGES, true)
            : 0;

        if ($startIdx === false) {
            $startIdx = 0;
        }

        foreach (array_slice(self::ALL_STAGES, $startIdx) as $key) {
            $this->emit('status', ['stage' => $key, 'state' => 'running']);
            $this->version->update(['stage_status' => array_merge(
                $this->version->stage_status ?? [],
                [$key => 'running']
            )]);

            try {
                $content = $this->runStage($key);
                $this->saveArtifact($key, $content);

                $this->emit('artifact', ['stage' => $key, 'content' => $content]);
                $this->emit('done', ['stage' => $key]);
                $this->emit('status', ['stage' => $key, 'state' => 'done']);
                $this->version->update(['stage_status' => array_merge(
                    $this->version->stage_status ?? [],
                    [$key => 'done']
                )]);
            } catch (Throwable $e) {
                $this->emit('error', ['stage' => $key, 'message' => $e->getMessage()]);
                $this->version->update(['stage_status' => array_merge(
                    $this->version->stage_status ?? [],
                    [$key => 'error']
                )]);
                if (! $auto) {
                    return;
                }
            }

            if (! $auto) {
                return;
            }
        }
    }

    private function runStage(string $key): string
    {
        $messages = $this->buildMessages($key);
        $buffer = '';

        $this->client->stream($messages, function (string $delta) use (&$buffer, $key) {
            $buffer .= $delta;
            $this->emit('token', ['stage' => $key, 'delta' => $delta]);
        });

        return $buffer;
    }

    private function buildMessages(string $stage): array
    {
        $v = $this->version;
        $target = $v->project->target ?? 'web';
        $system = $this->systemPrompt($stage, $target);
        $context = $this->contextPrompt($stage, $v);

        return [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $context],
        ];
    }

    private function systemPrompt(string $stage, string $target): string
    {
        $helpers = __DIR__ . '/../Prompts/helpers.php';
        if (file_exists($helpers)) require_once $helpers;

        $path = __DIR__ . "/../Prompts/{$stage}.php";
        if (!file_exists($path)) return '';

        $loader = require $path;
        return $loader($target);
    }

    private function contextPrompt(string $stage, Version $v): string
    {
        $idea = $v->project->idea;

        return match ($stage) {
            'analisa' => "Ide: {$idea}",
            'prd' => "Analisa: {$v->analysis}\n\nIde awal: {$idea}",
            'architecture' => "PRD: {$v->prd}",
            'erd' => "PRD: {$v->prd}\nArsitektur: {$v->architecture}",
            'phases' => "PRD: {$v->prd}\nArsitektur: {$v->architecture}\nERD: " . json_encode($v->erd ?? new \stdClass()),
            'master' => "PRD: {$v->prd}\nArsitektur: {$v->architecture}\nERD: " . json_encode($v->erd ?? new \stdClass()) . "\nPhases: " . json_encode($v->phases ?? []),
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
            'phases' => 'phases',
            'master' => 'master_prompt',
        ];

        $col = $map[$key] ?? null;
        if (! $col) {
            return;
        }

        $value = $content;
        if ($key === 'erd' || $key === 'phases' || $key === 'master') {
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $value = $decoded;
            } else {
                throw new \RuntimeException("JSON tidak valid untuk stage {$key}. Stage ditandai error.");
            }
        }

        $this->version->update([$col => $value]);
    }

    private function emit(string $event, array $data): void
    {
        fwrite($this->stdout, "event: {$event}\ndata: " . json_encode($data) . "\n\n");
        if (function_exists('flush')) {
            flush();
        }
    }
}
