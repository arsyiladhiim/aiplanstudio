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
            'status' => ['nullable', 'string', 'in:done,error'],
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

        $version->phaseProgress()->updateOrCreate(
            ['phase_key' => $data['phase_key']],
            [
                'done' => ($data['status'] ?? 'done') === 'done',
                'output' => $data['output'] ?? null,
            ]
        );

        return response()->json(['ok' => true, 'phase_key' => $data['phase_key'], 'status' => $data['status'] ?? 'done']);
    }
}
