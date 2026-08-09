<?php

namespace App\Http\Controllers;

use App\Models\Version;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class WebhookController extends Controller
{
    public function phaseComplete(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer'],
            'phase_key' => ['required', 'string'],
            'output' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'in:running,done,error,pending'],
        ]);

        $version = Version::whereHas('project', fn($q) => $q->where('id', $request->project_id))
            ->findOrFail($data['version_id']);

        $phases = $version->phases ?? [];
        $mobilePhases = $version->mobile_phases ?? [];
        $allPhases = array_merge(
            is_array($phases) ? $phases : [],
            is_array($mobilePhases) ? $mobilePhases : [],
        );
        $allowedKeys = array_column($allPhases, 'key');
        $phaseKey = $data['phase_key'];

        // Terima key aktual (mis. fase1_setup) ATAU bentuk phase-{n}/{n}/fase{n}
        // dengan mapping ke key aktual berdasarkan urutan (agent CLI umumnya pakai phase-1..5).
        if (! in_array($phaseKey, $allowedKeys)) {
            $resolved = null;
            if (preg_match('/^(?:phase|fase)?[-_]?(\d+)$/i', $phaseKey, $m)) {
                $idx = ((int) $m[1]) - 1;
                if (isset($allPhases[$idx]['key'])) {
                    $resolved = $allPhases[$idx]['key'];
                }
            }
            if ($resolved === null) {
                return response()->json([
                    'message' => 'Phase key tidak valid. Gunakan salah satu: '.implode(', ', $allowedKeys).' (atau phase-1..phase-'.count($allPhases).').',
                ], 422);
            }
            $phaseKey = $resolved;
        }

        $status = $data['status'] ?? 'done';
        $now = now();

        $progress = $version->phaseProgress()->firstOrNew(['phase_key' => $phaseKey]);
        if ($status === 'running' && ! $progress->started_at) {
            $progress->started_at = $now;
        }
        $progress->done = $status === 'done';
        $progress->status = $status;
        $progress->output = $data['output'] ?? $progress->output;
        if ($status === 'done' || $status === 'error') {
            $progress->finished_at = $now;
        }
        $progress->save();

        return response()->json(['ok' => true, 'phase_key' => $phaseKey, 'status' => $status]);
    }
}
