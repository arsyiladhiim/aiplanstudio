<?php

namespace App\Http\Controllers;

use App\Models\Version;
use App\Models\VersionStageEvidence;
use App\Services\TrackingInjector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

/**
 * CP-46.E — Export package ZIP untuk coding agent eksternal.
 * Berisi semua artifact + master prompt TERINJECT tracking + TRACKING.md snippet.
 *
 * Endpoint: GET /api/versions/{id}/export-package
 */
class ExportController extends Controller
{
    public function package(Request $request, int $id): StreamedResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->with('project')
            ->findOrFail($id);

        $projectTitle = Str::slug($version->project->title);
        $v = $version->version_no;
        $filename = "{$projectTitle}-v{$v}-package.zip";

        $injector = new TrackingInjector;
        $masterInjected = $version->master_prompt
            ? $injector->inject($version, $version->master_prompt)
            : null;
        $mobileMasterInjected = $version->mobile_master_prompt
            ? $injector->inject($version, $version->mobile_master_prompt)
            : null;

        return new StreamedResponse(function () use ($version, $projectTitle, $v, $masterInjected, $mobileMasterInjected) {
            $tmpPath = tempnam(sys_get_temp_dir(), 'export_pkg').'.zip';
            $zip = new ZipArchive;
            $zip->open($tmpPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

            $files = [
                'PRD.md' => $version->prd,
                'analysis.md' => $version->analysis,
                'architecture.md' => $version->architecture,
                'ERD.json' => $version->erd ? json_encode($version->erd, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
                'API-CONTRACT.json' => $version->api_contract ? json_encode($version->api_contract, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
                'DESIGN-SYSTEM.md' => $version->design_system,
                'DESIGN-SYSTEM-MOBILE.md' => $version->design_system_mobile,
                'STANDARDS.md' => $version->standards,
                'STANDARDS-MOBILE.md' => $version->mobile_standards,
                'PHASES.json' => $version->phases ? json_encode($version->phases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
                'PHASES-MOBILE.json' => $version->mobile_phases ? json_encode($version->mobile_phases, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
                'APP-SPEC-WEB.json' => $version->app_spec_web ? json_encode($version->app_spec_web, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
                'APP-SPEC-MOBILE.json' => $version->app_spec_mobile ? json_encode($version->app_spec_mobile, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null,
                'TESTING-STRATEGY.md' => $version->testing_strategy,
                'ENV-CONFIG.md' => $version->env_config,
                'SECURITY-CHECKLIST.md' => $version->security,
                'DEPLOYMENT.md' => $version->deployment,
                'OBSERVABILITY.md' => $version->observability,
                'AGENTS.md' => $version->agents,
                'AGENTS-MOBILE.md' => $version->mobile_agents,
                // Injected master prompt (server-side tracking live).
                'MASTER-INJECTED.md' => $masterInjected,
                'MASTER-MOBILE-INJECTED.md' => $mobileMasterInjected,
            ];

            foreach ($files as $name => $content) {
                if ($content !== null && $content !== '') {
                    $zip->addFromString($name, is_string($content) ? $content : (string) $content);
                }
            }

            // TRACKING.md — narasi + snippet referensi (snippet lengkap sudah di MASTER-INJECTED).
            $trackingMd = $this->buildTrackingMarkdown($version);
            $zip->addFromString('TRACKING.md', $trackingMd);

            // MANIFEST.json — daftar file + metadata untuk audit.
            $manifest = [
                'project_id' => $version->project_id,
                'version_id' => $version->id,
                'version_no' => $version->version_no,
                'target' => $version->project->target ?? 'web',
                'exported_at' => now()->toIso8601String(),
                'production_ready_at' => $version->production_ready_at?->toIso8601String(),
                'gate_states' => $version->gate_states ?? [],
                'files' => array_keys($files),
                'evidence_count' => VersionStageEvidence::where('version_id', $version->id)->count(),
            ];
            $zip->addFromString('MANIFEST.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            $zip->close();
            readfile($tmpPath);
            @unlink($tmpPath);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    public function productionReadiness(Request $request, int $id): JsonResponse
    {
        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($id);

        $registry = new \App\Services\StageGateRegistry;
        $result = $registry->check($version, 'verify.production_readiness');

        $evidenceCount = VersionStageEvidence::where('version_id', $version->id)->count();
        $gateStates = $version->gate_states ?? [];

        return response()->json([
            'data' => [
                'production_ready' => $result['passes'],
                'production_ready_at' => $version->production_ready_at?->toIso8601String(),
                'gate' => $result['gate'],
                'reason' => $result['reason'],
                'evidence_count' => $evidenceCount,
                'gate_states' => $gateStates,
                'evidence' => VersionStageEvidence::where('version_id', $version->id)
                    ->get()
                    ->map(fn ($e) => [
                        'stage_key' => $e->stage_key,
                        'tests_passed' => $e->tests_passed,
                        'lint_passed' => $e->lint_passed,
                        'build_passed' => $e->build_passed,
                        'migrate_passed' => $e->migrate_passed,
                        'security_passed' => $e->security_passed,
                        'perf_passed' => $e->perf_passed,
                        'updated_at' => $e->updated_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    private function buildTrackingMarkdown(Version $version): string
    {
        $url = rtrim((string) (config('app.tracking_base_url') ?: config('app.url')), '/').'/api/webhooks/phase-complete';

        return <<<MD
# Tracking Webhook — Quick Reference

Seluruh detail + TOKEN+SECRET tersinkronisasi ada di **MASTER-INJECTED.md** (server-side injected).

## Endpoint

```
POST {$url}
```

## Headers (WAJIB — case-sensitive)

- `Authorization: Bearer <TOKEN>` — dari MASTER-INJECTED.md
- `X-Token-Secret: <SECRET>` — dari MASTER-INJECTED.md
- `X-Timestamp: <unix_seconds>`
- `X-Signature: hmac_sha256("<X-Timestamp>.<raw_body>", "<X-Token-Secret>")`
- `Content-Type: application/json`

## Body per fase

```json
{"version_id": {$version->id}, "phase_key": "<key>", "status": "done", "output": "<ringkasan>"}
```

## Body per sub-item (halaman/menu/fitur/flow/api)

```json
{
  "version_id": {$version->id},
  "phase_key": "<key>",
  "task_key": "<sub_item_key>",
  "task_type": "halaman|menu|fitur|flow|api",
  "title": "<judul>",
  "status": "done",
  "output": "<ringkasan>"
}
```

## Status lifecycle

- `running` — saat mulai fase/sub-item.
- `done` — saat selesai.
- `error` — saat gagal.

## Error handling

Retry 3× backoff 1s/2s/4s, timeout 10s.
- 409 = sudah tercatat → lanjut.
- 422 = perbaiki key → retry.
- Gagal total → catat log, lanjut fase berikutnya (JANGAN berhenti permanen).

## Evidence endpoint (CP-46.B)

Untuk verify.* + smoke_test stages, kirim verification result ke:

```
POST {$url}/versions/{$version->id}/evidence
```

Body: `{stage_key, files_changed, tests_passed, lint_passed, build_passed, migrate_passed, security_passed, perf_passed, evidence_url?, notes?}`.

Header sama dengan webhook (HMAC).
MD;
    }
}
