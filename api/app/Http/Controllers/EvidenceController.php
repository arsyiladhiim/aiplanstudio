<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Version;
use App\Models\VersionStageEvidence;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CP-46.B — Evidence endpoint.
 * Agent POSTs stage-level evidence (files_changed, tests_passed, lint_passed, build_passed,
 * migrate_passed, security_passed, perf_passed) via ProjectApiToken HMAC.
 *
 * Per Q-A answer: 1 row per stage (UNIQUE constraint).
 */
class EvidenceController extends Controller
{
    public function store(Request $request, int $versionId): JsonResponse
    {
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        $projectToken = $request->attributes->get('project_token');

        if (! $timestamp || ! $signature) {
            return response()->json(['message' => 'Header X-Timestamp dan X-Signature wajib diisi.'], 401);
        }

        if (! $projectToken || ! $projectToken->verifySignature($timestamp, $request->getContent(), $signature)) {
            return response()->json(['message' => 'Signature tidak valid atau timestamp kedaluwarsa.'], 401);
        }

        // Replay protection.
        $replayKey = sprintf('evidence:%s:%s:%s', $projectToken->id, $timestamp, substr($signature, 0, 16));
        if (! Cache::add($replayKey, 1, 3600)) {
            return response()->json(['message' => 'Evidence duplikat terdeteksi.'], 409);
        }

        $data = $request->validate([
            'stage_key' => ['required', 'string', 'max:64'],
            'task_key' => ['nullable', 'string', 'max:128'],
            'files_changed' => ['nullable', 'array'],
            'files_changed.*' => ['string', 'max:512'],
            'tests_passed' => ['nullable', 'boolean'],
            'lint_passed' => ['nullable', 'boolean'],
            'build_passed' => ['nullable', 'boolean'],
            'migrate_passed' => ['nullable', 'boolean'],
            'security_passed' => ['nullable', 'boolean'],
            'perf_passed' => ['nullable', 'boolean'],
            'evidence_url' => ['nullable', 'string', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:65535'],
        ]);

        $version = Version::whereHas('project', fn ($q) => $q->where('id', $projectToken->project_id))
            ->findOrFail($versionId);

        // Whitelist stage_key ke Version::ALL_STAGES atau verify.* namespace.
        $allowedStages = array_merge(Version::ALL_STAGES, ['verify.review', 'smoke_test', 'verify.production_readiness']);
        if (! in_array($data['stage_key'], $allowedStages, true)) {
            return response()->json([
                'message' => 'stage_key tidak valid. Gunakan salah satu: '.implode(', ', $allowedStages),
            ], 422);
        }

        // Upsert (UNIQUE constraint enforces 1 row per stage).
        try {
            $evidence = DB::transaction(function () use ($data, $version, $request) {
                return VersionStageEvidence::updateOrCreate(
                    [
                        'version_id' => $version->id,
                        'stage_key' => $data['stage_key'],
                    ],
                    [
                        'project_id' => $version->project_id,
                        'task_key' => $data['task_key'] ?? null,
                        'files_changed' => $data['files_changed'] ?? null,
                        'tests_passed' => (bool) ($data['tests_passed'] ?? false),
                        'lint_passed' => (bool) ($data['lint_passed'] ?? false),
                        'build_passed' => (bool) ($data['build_passed'] ?? false),
                        'migrate_passed' => (bool) ($data['migrate_passed'] ?? false),
                        'security_passed' => (bool) ($data['security_passed'] ?? false),
                        'perf_passed' => (bool) ($data['perf_passed'] ?? false),
                        'evidence_url' => $data['evidence_url'] ?? null,
                        'notes' => $data['notes'] ?? null,
                    ]
                );
            });
        } catch (\Throwable $e) {
            Log::error('[evidence] upsert failed', [
                'version_id' => $version->id,
                'stage_key' => $data['stage_key'],
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Gagal menyimpan evidence.'], 500);
        }

        Activity::create([
            'project_id' => $version->project_id,
            'version_id' => $version->id,
            'user_id' => $version->project?->user_id,
            'action' => Activity::ACTION_ARTIFACT_SNAPSHOT ?? 'artifact_snapshot',
            'description' => "Evidence diterima untuk stage {$data['stage_key']}",
            'metadata' => [
                'stage_key' => $data['stage_key'],
                'tests_passed' => $evidence->tests_passed,
                'lint_passed' => $evidence->lint_passed,
                'build_passed' => $evidence->build_passed,
                'migrate_passed' => $evidence->migrate_passed,
                'security_passed' => $evidence->security_passed,
                'perf_passed' => $evidence->perf_passed,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'evidence_id' => $evidence->id,
            'stage_key' => $evidence->stage_key,
            'version_id' => $version->id,
            'updated_at' => $evidence->updated_at?->toIso8601String(),
        ], 200);
    }

    public function index(Request $request, int $versionId): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($versionId);

        $rows = VersionStageEvidence::where('version_id', $version->id)
            ->orderBy('stage_key')
            ->get()
            ->map(fn ($r) => [
                'stage_key' => $r->stage_key,
                'task_key' => $r->task_key,
                'files_changed' => $r->files_changed ?? [],
                'tests_passed' => $r->tests_passed,
                'lint_passed' => $r->lint_passed,
                'build_passed' => $r->build_passed,
                'migrate_passed' => $r->migrate_passed,
                'security_passed' => $r->security_passed,
                'perf_passed' => $r->perf_passed,
                'evidence_url' => $r->evidence_url,
                'notes' => $r->notes,
                'updated_at' => $r->updated_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $rows,
            'version_id' => $version->id,
        ]);
    }
}
