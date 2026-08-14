<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $checks = [
            'db' => $this->checkDb(),
            'redis' => $this->checkRedis(),
            'ai_provider' => $this->checkAiProvider(),
            'storage' => $this->checkStorage(),
        ];

        $allOk = collect($checks)->every(fn (array $c) => $c['ok']);
        $anyDown = collect($checks)->contains(fn (array $c) => ! $c['ok'] && ($c['critical'] ?? false));

        $status = $anyDown ? 'down' : ($allOk ? 'ok' : 'degraded');

        return response()->json([
            'status' => $status,
            'checks' => $checks,
            'checked_at' => now()->toIso8601String(),
        ]);
    }

    private function checkDb(): array
    {
        $start = microtime(true);
        try {
            DB::connection()->getPdo();
            return [
                'ok' => true,
                'critical' => true,
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
                'driver' => DB::connection()->getDriverName(),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'critical' => true, 'error' => $e->getMessage()];
        }
    }

    private function checkRedis(): array
    {
        $start = microtime(true);
        try {
            $pong = Redis::ping();
            $ok = $pong === true || $pong === 'PONG' || $pong === '+PONG' || $pong === 1;
            return [
                'ok' => $ok,
                'critical' => false,
                'latency_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'critical' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkAiProvider(): array
    {
        try {
            $provider = AiProvider::where('is_active', true)->first();
            if (! $provider) {
                return ['ok' => false, 'critical' => false, 'error' => 'No active provider'];
            }
            return [
                'ok' => true,
                'critical' => false,
                'model' => $provider->model,
                'base_url' => $provider->base_url,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'critical' => false, 'error' => $e->getMessage()];
        }
    }

    private function checkStorage(): array
    {
        try {
            $path = storage_path('logs');
            $free = @disk_free_space($path);
            $total = @disk_total_space($path);
            $usedPct = ($total && $free) ? round((($total - $free) / $total) * 100, 1) : null;
            return [
                'ok' => true,
                'critical' => false,
                'free_bytes' => $free,
                'total_bytes' => $total,
                'used_pct' => $usedPct,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'critical' => false, 'error' => $e->getMessage()];
        }
    }
}
