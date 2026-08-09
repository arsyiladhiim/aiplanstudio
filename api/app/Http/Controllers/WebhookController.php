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
        if (!in_array($data['phase_key'], $allowedKeys)) {
            return response()->json(['message' => 'Phase key tidak valid.'], 422);
        }

        $status = $data['status'] ?? 'done';
        $now = now();

        $progress = $version->phaseProgress()->firstOrNew(['phase_key' => $data['phase_key']]);
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

        return response()->json(['ok' => true, 'phase_key' => $data['phase_key'], 'status' => $status]);
    }
}
