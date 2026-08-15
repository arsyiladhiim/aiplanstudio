<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class WebhookController extends Controller
{
    public function phaseComplete(Request $request): JsonResponse
    {
        $timestamp = $request->header('X-Timestamp');
        $signature = $request->header('X-Signature');
        $projectToken = $request->attributes->get('project_token');

        if (! $timestamp || ! $signature) {
            return response()->json(['message' => 'Header X-Timestamp dan X-Signature wajib diisi.'], 401);
        }

        if (! $projectToken || ! $projectToken->verifySignature($timestamp, $request->getContent(), $signature)) {
            return response()->json(['message' => 'Signature webhook tidak valid atau timestamp kedaluwarsa (>300s).'], 401);
        }

        // Replay protection: cache key by token+timestamp+signature selama 1 jam.
        $replayKey = sprintf('webhook:%s:%s:%s', $projectToken->id, $timestamp, substr($signature, 0, 16));
        if (! Cache::add($replayKey, 1, 3600)) {
            return response()->json(['message' => 'Webhook duplikat terdeteksi. Permintaan sudah diproses.'], 409);
        }

        $data = $request->validate([
            'version_id' => ['required', 'integer'],
            'phase_key' => ['required', 'string'],
            'task_key' => ['nullable', 'string', 'max:255'],
            'task_type' => ['nullable', 'string', 'in:halaman,menu,fitur,flow,api'],
            'title' => ['nullable', 'string', 'max:255'],
            'output' => ['nullable', 'string', 'max:65535'],
            'status' => ['nullable', 'string', 'in:running,done,error,pending'],
        ]);

        $version = Version::whereHas('project', fn ($q) => $q->where('id', $request->project_id))
            ->findOrFail($data['version_id']);

        $phases = $version->phases ?? [];
        $mobilePhases = $version->mobile_phases ?? [];
        $allPhases = array_merge(
            is_array($phases) ? $phases : [],
            is_array($mobilePhases) ? $mobilePhases : [],
        );
        $allowedKeys = array_column($allPhases, 'key');
        $phaseKey = $data['phase_key'];

        if (! in_array($phaseKey, $allowedKeys, true)) {
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

        if (! empty($data['task_key'])) {
            $task = $progress->taskProgress()->firstOrNew(['task_key' => $data['task_key']]);
            $task->task_type = $data['task_type'] ?? $task->task_type ?? 'fitur';
            $task->title = $data['title'] ?? $task->title ?? $data['task_key'];
            if ($status === 'running' && ! $task->started_at) {
                $task->started_at = $now;
            }
            $task->status = $status;
            $task->output = $data['output'] ?? $task->output;
            if ($status === 'done' || $status === 'error') {
                $task->finished_at = $now;
            }
            $task->save();
        }

        Activity::create([
            'project_id' => $request->project_id,
            'version_id' => $version->id,
            'user_id' => $version->project->user_id,
            'action' => Activity::ACTION_WEBHOOK_RECEIVED,
            'description' => sprintf('Webhook phase-complete: %s/%s → %s', $phaseKey, $data['task_key'] ?? '-', $status),
            'metadata' => [
                'token_id' => $projectToken->id,
                'token_name' => $projectToken->name,
                'phase_key' => $phaseKey,
                'task_key' => $data['task_key'] ?? null,
                'status' => $status,
                'remote_ip' => $request->ip(),
            ],
        ]);

        return response()->json([
            'ok' => true,
            'phase_key' => $phaseKey,
            'task_key' => $data['task_key'] ?? null,
            'status' => $status,
        ]);
    }
}
