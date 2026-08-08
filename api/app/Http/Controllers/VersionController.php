<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Version;
use App\Services\AiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class VersionController extends Controller
{
    public function store(Request $request, int $projectId): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($projectId);

        $version = DB::transaction(function () use ($project) {
            $locked = Project::where('id', $project->id)->lockForUpdate()->firstOrFail();
            $next = ($locked->versions()->max('version_no') ?? 0) + 1;

            return $locked->versions()->create([
                'version_no' => $next,
                'stage_status' => Version::defaultStageStatus(),
            ]);
        });

        $project->logActivity(Activity::ACTION_CREATED_VERSION, "Membuat versi v{$version->version_no}", $version->id);

        return response()->json($version, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['phaseProgress', 'project'])
            ->findOrFail($id);

        return response()->json($version);
    }

    public function togglePhase(Request $request, int $id, string $phaseKey): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $phases = $version->phases ?? [];
        $mobilePhases = $version->mobile_phases ?? [];
        $allPhases = array_merge(
            is_array($phases) ? $phases : [],
            is_array($mobilePhases) ? $mobilePhases : [],
        );
        $allowedKeys = array_column($allPhases, 'key');
        if (! in_array($phaseKey, $allowedKeys)) {
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

        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
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
                $zip = new ZipArchive;
                $tmpPath = tempnam(sys_get_temp_dir(), 'export').'.zip';
                $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
                $zip->addFromString("{$projectTitle}-v{$v}.md", $this->buildMarkdown($version));
                $zip->addFromString('erd.json', json_encode($version->erd ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                if ($version->mobile_standards) {
                    $zip->addFromString('mobile-standards.md', $version->mobile_standards);
                }
                if ($version->mobile_agents) {
                    $zip->addFromString('mobile-agents.md', $version->mobile_agents);
                }
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
            '',
            '## Pertanyaan Klarifikasi',
            $v->pertanyaan ?? '_Belum ada_',
            '',
        ];

        if ($v->answers) {
            $lines[] = '### Jawaban';
            foreach ($v->answers as $q => $a) {
                $lines[] = "- **{$q}:** {$a}";
            }
            $lines[] = '';
        }

        $lines = array_merge($lines, [
            '## Analisa',
            $v->analysis ?? '_Belum ada_',
            '',
            '## PRD',
            $v->prd ?? '_Belum ada_',
            '',
            '## Arsitektur & Stack',
            $v->architecture ?? '_Belum ada_',
            '',
            '## ERD',
            $v->erd ? '```json'.PHP_EOL.json_encode($v->erd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL.'```' : '_Belum ada_',
            '',
            '## Phase Breakdown',
        ]);

        foreach (($v->phases ?? []) as $ph) {
            $lines[] = "### {$ph['title']}";
            $lines[] = $ph['prompt'] ?? '';
            $lines[] = '';
        }

        $lines[] = '## Master Prompt';
        $lines[] = $v->master_prompt ?? '_Belum ada_';

        if ($v->mobile_phases || $v->mobile_master_prompt) {
            $lines[] = '';
            $lines[] = '## Mobile (Flutter)';
            $lines[] = '';
            $lines[] = '### Mobile Phase Breakdown';
            foreach (($v->mobile_phases ?? []) as $ph) {
                $lines[] = "#### {$ph['title']}";
                $lines[] = $ph['prompt'] ?? '';
                $lines[] = '';
            }
            $lines[] = '## Mobile Master Prompt';
            $lines[] = $v->mobile_master_prompt ?? '_Belum ada_';
            $lines[] = '';
            $lines[] = '## Mobile Standards';
            $lines[] = $v->mobile_standards ?? '_Belum ada_';
            $lines[] = '';
            $lines[] = '## Mobile Agents';
            $lines[] = $v->mobile_agents ?? '_Belum ada_';
        }

        return implode(PHP_EOL, $lines);
    }

    public function updateArtifact(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'stage' => ['required', 'string', 'in:pertanyaan,analisa,prd,architecture,erd,master_web,master_mobile'],
            'content' => ['required', 'string'],
        ]);

        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $colMap = [
            'pertanyaan' => 'pertanyaan',
            'analisa' => 'analysis',
            'prd' => 'prd',
            'architecture' => 'architecture',
            'erd' => 'erd',
            'master_web' => 'master_prompt',
            'master_mobile' => 'mobile_master_prompt',
        ];

        $col = $colMap[$data['stage']];
        $value = $data['content'];

        if ($data['stage'] === 'erd') {
            $decoded = json_decode($value, true);
            if ($decoded !== null) {
                $value = $decoded;
            }
        }

        $version->update([$col => $value]);

        return response()->json(['ok' => true, 'message' => 'Artifact diperbarui.']);
    }

    public function diff(Request $request, int $id): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $otherId = $request->query('compare');
        if (! $otherId) {
            return response()->json(['message' => 'Parameter compare required.'], 422);
        }

        $other = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail((int) $otherId);

        $fields = ['pertanyaan', 'analysis', 'prd', 'architecture', 'erd', 'master_prompt', 'mobile_master_prompt', 'mobile_phases', 'mobile_standards', 'mobile_agents'];
        $labels = [
            'pertanyaan' => 'Pertanyaan',
            'analysis' => 'Analisa',
            'prd' => 'PRD',
            'architecture' => 'Arsitektur',
            'erd' => 'ERD',
            'master_prompt' => 'Master Prompt',
            'mobile_master_prompt' => 'Mobile Master Prompt',
            'mobile_phases' => 'Mobile Phase Breakdown',
            'mobile_standards' => 'Mobile Standards',
            'mobile_agents' => 'Mobile Agents',
        ];

        $diffs = [];
        foreach ($fields as $f) {
            $a = $version->{$f};
            $b = $other->{$f};
            $diffs[] = [
                'field' => $f,
                'label' => $labels[$f],
                'left' => is_string($a) ? $a : (is_null($a) ? null : json_encode($a, JSON_PRETTY_PRINT)),
                'right' => is_string($b) ? $b : (is_null($b) ? null : json_encode($b, JSON_PRETTY_PRINT)),
                'changed' => json_encode($a) !== json_encode($b),
            ];
        }

        return response()->json([
            'left' => ['id' => $version->id, 'version_no' => $version->version_no, 'project_title' => $version->project->title],
            'right' => ['id' => $other->id, 'version_no' => $other->version_no, 'project_title' => $other->project->title],
            'diffs' => $diffs,
        ]);
    }

    public function downloadStandards(Request $request, int $id): Response
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
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
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $content = $version->agents ?? 'Belum ada. Jalankan pipeline sampai stage Agents.';
        $name = 'AGENTS.md';

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    public function updateAnswers(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'answers' => ['required', 'array'],
            'answers.*' => ['required', 'string'],
        ]);

        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $version->update(['answers' => $data['answers']]);

        return response()->json(['ok' => true, 'message' => 'Jawaban disimpan.']);
    }

    public function downloadMobileStandards(Request $request, int $id): Response
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $content = $version->mobile_standards ?? 'Belum ada. Jalankan pipeline untuk mobile.';
        $name = 'STANDARDS-MOBILE.md';

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    public function downloadMobileAgents(Request $request, int $id): Response
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $content = $version->mobile_agents ?? 'Belum ada. Jalankan pipeline untuk mobile.';
        $name = 'AGENTS-MOBILE.md';

        return response($content, 200, [
            'Content-Type' => 'text/markdown; charset=utf-8',
            'Content-Disposition' => "attachment; filename=\"{$name}\"",
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $project = $version->project;
        $versionNo = $version->version_no;

        try {
            DB::transaction(function () use ($version, $project) {
                $locked = Project::where('id', $project->id)->lockForUpdate()->firstOrFail();
                if ($locked->versions()->count() <= 1) {
                    throw new \RuntimeException('Tidak bisa menghapus versi terakhir.');
                }
                $version->delete();
            });
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $project->logActivity(Activity::ACTION_DELETED_VERSION, "Menghapus versi v{$versionNo}");

        return response()->json(null, 204);
    }

    public function regenerateMobileStandards(Request $request, AiClient $client, int $id): JsonResponse
    {
        try {
            $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
                ->with('project')
                ->findOrFail($id);

            if (! $client->isConfigured()) {
                return response()->json(['ok' => false, 'message' => 'AI Provider belum dikonfigurasi.'], 400);
            }

            $target = 'mobile';
            $idea = $version->project->idea ?? '';

            $ctx = "### Ide Aplikasi\n{$idea}\n\n### Target Platform\n{$target}";
            $ctx .= "\n\n### Tech Stack\nFlutter + Dart + Riverpod + GoRouter + Material Design 3 + drift/sqflite";
            $ctx .= "\n\n### Arsitektur\n".($version->architecture ?? 'Belum ada data arsitektur.');
            $ctx .= "\n\n### ERD\n".json_encode($version->erd ?? new \stdClass, JSON_PRETTY_PRINT);

            $promptsDir = __DIR__.'/../../Prompts';
            $helpersFile = $promptsDir.'/helpers.php';
            if (file_exists($helpersFile)) {
                require_once $helpersFile;
            }

            $standardsPromptFile = $promptsDir.'/standards.php';
            $agentsPromptFile = $promptsDir.'/agents.php';

            if (! file_exists($standardsPromptFile) || ! file_exists($agentsPromptFile)) {
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

            $version->update(['mobile_standards' => $standards, 'mobile_agents' => $agents]);

            return response()->json([
                'ok' => true,
                'standards' => ! empty($standards),
                'agents' => ! empty($agents),
            ]);
        } catch (\Throwable $e) {
            Log::error('[regenerateMobileStandards] Error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'ok' => false,
                'message' => 'Gagal meregenerasi standar mobile. Coba lagi nanti.',
            ], 500);
        }
    }

    public function regenerateStandards(Request $request, AiClient $client, int $id): JsonResponse
    {
        try {
            $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
                ->with('project')
                ->findOrFail($id);

            if (! $client->isConfigured()) {
                return response()->json(['ok' => false, 'message' => 'AI Provider belum dikonfigurasi.'], 400);
            }

            $target = $version->project->target ?? 'web';
            $idea = $version->project->idea ?? '';
            $stack = $version->project->stack ?? '';

            $ctx = "### Ide Aplikasi\n{$idea}\n\n### Target Platform\n{$target}";
            if ($stack) {
                $ctx .= "\n\n### Tech Stack Pilihan\n{$stack}";
            }
            $ctx .= "\n\n### Arsitektur\n".($version->architecture ?? 'Belum ada data arsitektur.');
            $ctx .= "\n\n### ERD\n".json_encode($version->erd ?? new \stdClass, JSON_PRETTY_PRINT);

            $promptsDir = __DIR__.'/../../Prompts';
            $helpersFile = $promptsDir.'/helpers.php';
            if (file_exists($helpersFile)) {
                require_once $helpersFile;
            }

            $standardsPromptFile = $promptsDir.'/standards.php';
            $agentsPromptFile = $promptsDir.'/agents.php';

            if (! file_exists($standardsPromptFile) || ! file_exists($agentsPromptFile)) {
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
                'standards' => ! empty($standards),
                'agents' => ! empty($agents),
            ]);
        } catch (\Throwable $e) {
            Log::error('[regenerateStandards] Error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'ok' => false,
                'message' => 'Gagal meregenerasi standar. Coba lagi nanti.',
            ], 500);
        }
    }
}
