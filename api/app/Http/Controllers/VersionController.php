<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Version;
use App\Services\AiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class VersionController extends Controller
{
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($projectId);

        $next = $project->nextVersionNo();
        $version = $project->versions()->create([
            'version_no' => $next,
            'stage_status' => Version::defaultStageStatus(),
        ]);

        return response()->json($version, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $version = Version::whereHas('project', fn($q) => $q->where('user_id', $request->user()->id))
            ->with(['phaseProgress', 'project'])
            ->findOrFail($id);

        return response()->json($version);
    }

    public function togglePhase(Request $request, int $id, string $phaseKey): JsonResponse
    {
        $version = Version::whereHas('project', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $phases = $version->phases ?? [];
        $allowedKeys = array_column(is_array($phases) ? $phases : [], 'key');
        if (!in_array($phaseKey, $allowedKeys)) {
            return response()->json(['message' => 'Phase key tidak valid.'], 422);
        }

        $data = $request->validate(['done' => ['required', 'boolean']]);

        $progress = $version->phaseProgress()->updateOrCreate(
            ['phase_key' => $phaseKey],
            ['done' => $data['done']]
        );

        return response()->json($progress);
    }

    public function export(Request $request, int $id): JsonResponse|StreamedResponse|Response
    {
        $request->validate(['format' => ['sometimes', 'string', 'in:md,zip']]);

        $version = Version::whereHas('project', fn($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $format = $request->query('format', 'md');
        $projectTitle = Str::slug($version->project->title);
        $v = $version->version_no;

        if ($format === 'md') {
            $content = $this->buildMarkdown($version);
            $name = "{$projectTitle}-v{$v}.md";

            return response($content, 200, [
                'Content-Type' => 'text/markdown; charset=utf-8',
                'Content-Disposition' => "attachment; filename=\"{$name}\"",
            ]);
        }

        if ($format === 'zip') {
            return new StreamedResponse(function () use ($version, $projectTitle, $v) {
                $zip = new ZipArchive();
                $tmpPath = tempnam(sys_get_temp_dir(), 'export') . '.zip';
                $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $zip->addFromString("{$projectTitle}-v{$v}.md", $this->buildMarkdown($version));
                $zip->addFromString('erd.json', json_encode($version->erd ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $zip->close();

                readfile($tmpPath);
                @unlink($tmpPath);
            }, 200, [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => "attachment; filename=\"{$projectTitle}-v{$v}.zip\"",
            ]);
        }

        return response()->json(['message' => 'Format tidak didukung.'], 422);
    }

    private function buildMarkdown(Version $v): string
    {
        $lines = [
            "# {$v->project->title}",
            "Versi: v{$v->version_no}",
            "",
            "## Analisa",
            $v->analysis ?? '_Belum ada_',
            "",
            "## PRD",
            $v->prd ?? '_Belum ada_',
            "",
            "## Arsitektur & Stack",
            $v->architecture ?? '_Belum ada_',
            "",
            "## ERD",
            $v->erd ? '```json' . PHP_EOL . json_encode($v->erd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL . '```' : '_Belum ada_',
            "",
            "## Phase Breakdown",
        ];

        foreach (($v->phases ?? []) as $ph) {
            $lines[] = "### {$ph['title']}";
            $lines[] = $ph['prompt'] ?? '';
            $lines[] = '';
        }

        $lines[] = "## Master Prompt";
        $lines[] = $v->master_prompt ?? '_Belum ada_';

        return implode(PHP_EOL, $lines);
    }

    public function downloadStandards(Request $request, int $id): Response
    {
        $version = Version::whereHas('project', fn($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $content = $version->standards ?? 'Belum ada. Jalankan pipeline sampai stage Standards.';
        $name = 'STANDARDS.md';

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    public function downloadAgents(Request $request, int $id): Response
    {
        $version = Version::whereHas('project', fn($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $content = $version->agents ?? 'Belum ada. Jalankan pipeline sampai stage Agents.';
        $name = 'AGENTS.md';

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    public function regenerateStandards(Request $request, AiClient $client, int $id): JsonResponse
    {
        try {
            $version = Version::whereHas('project', fn($q) => $q->where('user_id', $request->user()->id))
                ->with('project')
                ->findOrFail($id);

            if (!$client->isConfigured()) {
                return response()->json(['ok' => false, 'message' => 'AI Provider belum dikonfigurasi.'], 400);
            }

            $target = $version->project->target ?? 'web';
            $idea = $version->project->idea ?? '';
            $stack = $version->project->stack ?? '';

            $ctx = "### Ide Aplikasi\n{$idea}\n\n### Target Platform\n{$target}";
            if ($stack) $ctx .= "\n\n### Tech Stack Pilihan\n{$stack}";
            $ctx .= "\n\n### Arsitektur\n" . ($version->architecture ?? 'Belum ada data arsitektur.');
            $ctx .= "\n\n### ERD\n" . json_encode($version->erd ?? new \stdClass(), JSON_PRETTY_PRINT);

            $promptsDir = __DIR__ . '/../../Prompts';
            $helpersFile = $promptsDir . '/helpers.php';
            if (file_exists($helpersFile)) require_once $helpersFile;

            $standardsPromptFile = $promptsDir . '/standards.php';
            $agentsPromptFile = $promptsDir . '/agents.php';

            if (!file_exists($standardsPromptFile) || !file_exists($agentsPromptFile)) {
                return response()->json(['ok' => false, 'message' => 'Prompt files not found.'], 500);
            }

            $standardsPrompt = require $standardsPromptFile;
            $agentsPrompt = require $agentsPromptFile;

            $standards = $client->complete([
                ['role' => 'system', 'content' => $standardsPrompt($target)],
                ['role' => 'user', 'content' => $ctx],
            ]);

            $agents = $client->complete([
                ['role' => 'system', 'content' => $agentsPrompt($target)],
                ['role' => 'user', 'content' => $ctx],
            ]);

            $version->update(['standards' => $standards, 'agents' => $agents]);

            return response()->json([
                'ok' => true,
                'standards' => !empty($standards),
                'agents' => !empty($agents),
            ]);
        } catch (\Throwable $e) {
            \Log::error("[regenerateStandards] Error: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return response()->json([
                'ok' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
