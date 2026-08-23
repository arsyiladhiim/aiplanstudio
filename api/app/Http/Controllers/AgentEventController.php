<?php

namespace App\Http\Controllers;

use App\Models\AgentEvent;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** CP-44 CP-07: ingest event dari coding agent eksternal (auth.project-token). */
class AgentEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'version_id' => ['required', 'integer'],
            'run_id' => ['required', 'string', 'max:64'],
            'event_id' => ['required', 'string', 'max:128'],
            'event' => ['required', 'string', 'in:'.implode(',', AgentEvent::EVENTS)],
            'phase_key' => ['nullable', 'string', 'max:128'],
            'task_key' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:32'],
            'payload' => ['nullable', 'array'],
        ]);

        $version = Version::whereHas('project', fn ($q) => $q->where('id', $request->project_id))
            ->find($data['version_id']);
        if (! $version) {
            return response()->json(['message' => 'Version tidak ditemukan untuk project token ini.'], 422);
        }

        // Idempotency: event_id unik — replay dianggap sukses (202) tanpa duplikasi.
        $existing = AgentEvent::where('event_id', $data['event_id'])->first();
        if ($existing) {
            return response()->json(['ok' => true, 'duplicate' => true, 'event_id' => $data['event_id']], 202);
        }

        $event = AgentEvent::create([
            'project_id' => $request->project_id,
            'version_id' => $version->id,
            'run_id' => $data['run_id'],
            'event_id' => $data['event_id'],
            'event' => $data['event'],
            'phase_key' => $data['phase_key'] ?? null,
            'task_key' => $data['task_key'] ?? null,
            'status' => $data['status'] ?? null,
            'payload' => $data['payload'] ?? null,
        ]);

        return response()->json(['ok' => true, 'id' => $event->id, 'event_id' => $event->event_id], 201);
    }

    /** Feed untuk frontend (session auth). */
    public function index(Request $request, int $versionId): JsonResponse
    {
        $events = AgentEvent::where('version_id', $versionId)
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'run_id', 'event_id', 'event', 'phase_key', 'task_key', 'status', 'payload', 'created_at']);

        return response()->json(['data' => $events]);
    }
}
