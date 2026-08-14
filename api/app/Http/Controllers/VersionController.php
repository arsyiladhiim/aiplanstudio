<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
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
        $data = $request->validate([
            'strategy' => ['sometimes', 'string', 'in:blank,from_last'],
            'baseline_notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $project = Project::where('user_id', $request->user()->id)->findOrFail($projectId);

        $version = DB::transaction(function () use ($project, $data) {
            $locked = Project::where('id', $project->id)->lockForUpdate()->firstOrFail();
            $next = ($locked->versions()->max('version_no') ?? 0) + 1;

            $strategy = $data['strategy'] ?? 'from_last';
            $source = $strategy === 'from_last'
                ? $locked->versions()->latest('version_no')->first()
                : null;

            $attrs = [
                'version_no' => $next,
                'stage_status' => Version::defaultStageStatus(),
            ];

            if ($source) {
                // Clone baseline: semua artefak + jawaban + fase dari versi terakhir.
                // Status stage yang sudah done ikut disalin agar pengembangan berlanjut,
                // bukan mulai dari nol.
                $cloneFields = [
                    'pertanyaan', 'answers', 'pertanyaan_mobile', 'mobile_answers',
                    'analysis', 'prd', 'architecture', 'erd', 'api_contract',
                    'phases', 'master_prompt', 'standards', 'agents',
                    'mobile_phases', 'mobile_master_prompt', 'mobile_standards', 'mobile_agents',
                ];
                foreach ($cloneFields as $f) {
                    $attrs[$f] = $source->{$f};
                }
                $attrs['stage_status'] = $source->stage_status;
                $attrs['source_version_id'] = $source->id;
                $attrs['baseline_notes'] = $data['baseline_notes'] ?? "Dibuat dari v{$source->version_no} (baseline pengembangan)";
            }

            return $locked->versions()->create($attrs);
        });

        $action = $version->source_version_id
            ? "Membuat versi v{$version->version_no} (baseline dari v{$version->source?->version_no})"
            : "Membuat versi v{$version->version_no}";
        $project->logActivity(Activity::ACTION_CREATED_VERSION, $action, $version->id);

        return response()->json($version->fresh(['project']), 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with(['phaseProgress', 'project'])
            ->findOrFail($id);

        return response()->json($version);
    }

    public function phaseProgressStream(Request $request, int $id): StreamedResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('phaseProgress')
            ->findOrFail($id);

        $phases = array_merge(
            is_array($version->phases) ? $version->phases : [],
            is_array($version->mobile_phases) ? $version->mobile_phases : [],
        );
        $totalPhases = count($phases);

        return new StreamedResponse(function () use ($version, $totalPhases) {
            $emit = function (string $event, array $data): void {
                echo "event: {$event}\n";
                echo 'data: '.json_encode($data, JSON_UNESCAPED_UNICODE)."\n\n";
                echo ": ping\n\n";
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            };

            if ($totalPhases === 0) {
                $emit('done', ['completed' => 0, 'total' => 0]);

                return;
            }

            $lastSig = '';
            $ticks = 0;
            $maxTicks = 600; // 20 minutes max

            while ($ticks < $maxTicks) {
                if (connection_aborted()) {
                    break;
                }
                $ticks++;
                $progress = $version->phaseProgress()->with('taskProgress')->get();
                $sig = $progress->map(fn ($p) => $p->phase_key.':'.$p->status.':'.$p->done.':'.$p->output.'|'.$p->taskProgress->map(fn ($t) => $t->task_key.':'.$t->status)->implode(';'))->implode('|');

                if ($sig !== $lastSig) {
                    $lastSig = $sig;
                    $doneCount = 0;
                    foreach ($progress as $p) {
                        $emit('phase_progress', [
                            'phase_key' => $p->phase_key,
                            'status' => $p->status,
                            'done' => (bool) $p->done,
                            'output' => $p->output,
                            'started_at' => $p->started_at?->toIso8601String(),
                            'finished_at' => $p->finished_at?->toIso8601String(),
                            'tasks' => $p->taskProgress->map(fn ($t) => [
                                'task_key' => $t->task_key,
                                'task_type' => $t->task_type,
                                'title' => $t->title,
                                'status' => $t->status,
                                'output' => $t->output,
                                'started_at' => $t->started_at?->toIso8601String(),
                                'finished_at' => $t->finished_at?->toIso8601String(),
                            ])->toArray(),
                        ]);
                        if ($p->done) {
                            $doneCount++;
                        }
                    }
                    if ($doneCount >= $totalPhases) {
                        $emit('done', ['completed' => $doneCount, 'total' => $totalPhases]);
                        break;
                    }
                }

                usleep(2_000_000); // 2 seconds
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
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
        $now = now();

        $updateData = ['done' => $data['done']];
        $updateData['status'] = $data['done'] ? 'done' : 'pending';
        if ($data['done']) {
            $updateData['finished_at'] = $now;
        } else {
            $updateData['finished_at'] = null;
        }

        $progress = $version->phaseProgress()->firstOrNew(['phase_key' => $phaseKey]);
        if (! $progress->started_at && $data['done']) {
            $updateData['started_at'] = $now;
        }
        $progress->fill($updateData);
        $progress->save();

        return response()->json($progress);
    }

    public function toggleTask(Request $request, int $id, string $taskKey): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $data = $request->validate([
            'done' => ['required', 'boolean'],
            'phase_key' => ['nullable', 'string'],
        ]);

        $query = $version->phaseProgress()->when(
            $data['phase_key'] ?? null,
            fn ($q, $pk) => $q->where('phase_key', $pk)
        );

        $progress = $query->whereHas('taskProgress', fn ($q) => $q->where('task_key', $taskKey))->firstOrFail();
        $task = $progress->taskProgress()->where('task_key', $taskKey)->firstOrFail();

        $now = now();
        if ($data['done']) {
            $task->status = 'done';
            $task->finished_at = $now;
            if (! $task->started_at) {
                $task->started_at = $now;
            }
        } else {
            $task->status = 'pending';
            $task->finished_at = null;
        }
        $task->save();

        return response()->json($task);
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

    public function buildMarkdownPublic(Version $v): string
    {
        $v->loadMissing('project');

        return $this->buildMarkdown($v);
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
        ]);

        if ($v->api_contract) {
            $lines[] = '## API Contract';
            $lines[] = '```json'.PHP_EOL.json_encode($v->api_contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL.'```';
            $lines[] = '';
        }

        if ($v->pertanyaan_mobile || $v->mobile_answers) {
            $lines[] = '## Pertanyaan Mobile (klarifikasi)';
            $lines[] = $v->pertanyaan_mobile ?? '_Belum ada_';
            $lines[] = '';
            if ($v->mobile_answers) {
                $lines[] = '### Jawaban Mobile';
                foreach ($v->mobile_answers as $q => $a) {
                    $lines[] = "- **{$q}:** {$a}";
                }
                $lines[] = '';
            }
        }

        $lines[] = '## Phase Breakdown';

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
            'stage' => ['required', 'string', 'in:'.implode(',', Version::ALL_STAGES)],
            'content' => ['required', 'string'],
        ]);

        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);
        $this->authorize('update', $version);

        $colMap = [
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

        $fields = ['pertanyaan', 'answers', 'analysis', 'prd', 'architecture', 'erd', 'api_contract', 'phases', 'standards', 'master_prompt', 'agents',
            'pertanyaan_mobile', 'mobile_answers', 'mobile_phases', 'mobile_standards', 'mobile_master_prompt', 'mobile_agents'];
        $labels = [
            'pertanyaan' => 'Pertanyaan',
            'answers' => 'Jawaban Klarifikasi',
            'analysis' => 'Analisa',
            'prd' => 'PRD',
            'architecture' => 'Arsitektur',
            'erd' => 'ERD',
            'api_contract' => 'API Contract',
            'phases' => 'Phase Breakdown',
            'standards' => 'Standards',
            'master_prompt' => 'Master Prompt',
            'agents' => 'Agents',
            'pertanyaan_mobile' => 'Pertanyaan Mobile',
            'mobile_answers' => 'Jawaban Mobile',
            'mobile_phases' => 'Mobile Phase Breakdown',
            'mobile_standards' => 'Mobile Standards',
            'mobile_master_prompt' => 'Mobile Master Prompt',
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
            'answers.*' => ['required', 'string', 'max:10000'],
            'mobile_answers' => ['sometimes', 'array'],
            'mobile_answers.*' => ['required', 'string', 'max:10000'],
        ]);

        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $updates = ['answers' => $data['answers']];
        if (isset($data['mobile_answers'])) {
            $updates['mobile_answers'] = $data['mobile_answers'];
        }

        $version->update($updates);

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
        $this->authorize('delete', $version);

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

    public function restartFromAnalisa(Request $request, AiClient $client, int $id): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        if (! $client->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'AI Provider belum dikonfigurasi.'], 400);
        }

        try {
            $status = $version->stage_status ?? [];
            foreach (['pertanyaan', 'analisa'] as $skip) {
                if (($status[$skip] ?? null) !== 'done') {
                    $status[$skip] = 'done';
                }
            }
            $version->update(['stage_status' => $status]);

            $stream = fopen('php://memory', 'w+');
            $runner = new PipelineRunner($version->fresh(['project']), $client, $stream);
            $runner->run('prd', true);
            rewind($stream);
            $sse = stream_get_contents($stream);
            fclose($stream);

            $version->refresh();

            $version->project->logActivity(
                Activity::ACTION_REGENERATE_STAGE,
                "Restart dari analisa di v{$version->version_no} (skip pertanyaan)",
                $version->id,
                ['mode' => 'skip_pertanyaan'],
            );

            return response()->json([
                'ok' => true,
                'mode' => 'skip_pertanyaan',
                'skipped' => ['pertanyaan', 'analisa'],
                'started_at' => 'prd',
                'stream_tail' => mb_substr((string) $sse, -4096),
            ]);
        } catch (\Throwable $e) {
            Log::error('[restartFromAnalisa] Error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'ok' => false,
                'message' => 'Gagal restart pipeline. Coba lagi nanti.',
            ], 500);
        }
    }

    public function regenerateStage(Request $request, AiClient $client, int $id): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $data = $request->validate([
            'stage' => ['required', 'string', 'in:'.implode(',', Version::ALL_STAGES)],
        ]);
        $stage = $data['stage'];

        if (! $client->isConfigured()) {
            return response()->json(['ok' => false, 'message' => 'AI Provider belum dikonfigurasi.'], 400);
        }

        $snapshot = $version->only([
            'master_prompt', 'mobile_master_prompt', 'phases', 'mobile_phases',
            'standards', 'mobile_standards', 'agents', 'mobile_agents',
            'stage_status', 'updated_at',
        ]);

        try {
            $stream = fopen('php://memory', 'w+');
            $runner = new PipelineRunner($version->fresh(['project']), $client, $stream);
            $runner->run($stage, true);
            rewind($stream);
            $sse = stream_get_contents($stream);
            fclose($stream);

            $version->refresh();
            $finalStatus = $version->stage_status;
            $hasError = ($finalStatus[$stage] ?? null) === 'error';

            if ($hasError) {
                $version->fill(array_intersect_key($snapshot, $version->getAttributes()));
                $version->stage_status = $snapshot['stage_status'];
                $version->save();
            }

            $version->project->logActivity(
                Activity::ACTION_REGENERATE_STAGE,
                "Regenerate stage {$stage} di v{$version->version_no}".($hasError ? ' (rolled back)' : ''),
                $version->id,
                ['stage' => $stage, 'status' => $finalStatus[$stage] ?? 'pending', 'rolled_back' => $hasError],
            );

            return response()->json([
                'ok' => ! $hasError,
                'stage' => $stage,
                'status' => $finalStatus[$stage] ?? 'pending',
                'rolled_back' => $hasError,
                'stream_tail' => mb_substr((string) $sse, -4096),
            ]);
        } catch (\Throwable $e) {
            $version->fill(array_intersect_key($snapshot, $version->getAttributes()));
            $version->stage_status = $snapshot['stage_status'];
            $version->save();

            Log::error('[regenerateStage] Error: '.$e->getMessage().' in '.$e->getFile().':'.$e->getLine());

            return response()->json([
                'ok' => false,
                'message' => 'Gagal meregenerasi stage. State dikembalikan ke sebelum regenerate.',
            ], 500);
        }
    }
}
