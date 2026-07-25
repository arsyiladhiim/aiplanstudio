<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
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
        $validStages = ['analisa', 'prd', 'architecture', 'erd', 'phases', 'master'];
        if (!in_array($phaseKey, $validStages)) {
            return response()->json(['message' => 'Phase key tidak valid.'], 422);
        }

        $version = Version::whereHas('project', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $data = $request->validate(['done' => ['required', 'boolean']]);

        $progress = $version->phaseProgress()->updateOrCreate(
            ['phase_key' => $phaseKey],
            ['done' => $data['done']]
        );

        return response()->json($progress);
    }

    public function export(Request $request, int $id): JsonResponse|\Symfony\Component\HttpFoundation\StreamedResponse
    {
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
            $tmpPath = tempnam(sys_get_temp_dir(), 'export') . '.zip';
            $zip = new ZipArchive();
            $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString("{$projectTitle}-v{$v}.md", $this->buildMarkdown($version));
            $zip->addFromString('erd.json', json_encode($version->erd ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $zip->close();

            $headers = ['Content-Type' => 'application/zip', 'Content-Disposition' => "attachment; filename=\"{$projectTitle}-v{$v}.zip\""];
            $response = response()->download($tmpPath, "{$projectTitle}-v{$v}.zip", $headers);
            register_shutdown_function(fn() => @unlink($tmpPath));
            return $response;
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
}
