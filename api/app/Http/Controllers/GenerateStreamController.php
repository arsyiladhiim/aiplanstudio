<?php

namespace App\Http\Controllers;

use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateStreamController extends Controller
{
    public function __invoke(Request $request, AiClient $client): StreamedResponse
    {
        $versionId = $request->input('version', $request->query('version'));
        $stage = $request->input('stage', $request->query('stage'));
        $auto = ($request->input('auto', $request->query('auto', '0')) === '1');
        $lite = ($request->input('lite', $request->query('lite', '0')) === '1');

        if (! $versionId || ! $stage) {
            abort(422, 'Parameter "version" dan "stage" wajib diisi.');
        }

        $validStages = Version::ALL_STAGES;
        if (! in_array($stage, $validStages)) {
            abort(422, 'Stage tidak valid. Pilih: '.implode(', ', $validStages));
        }

        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($versionId);

        // 55-2.6: Postgres advisory lock per (version,stage) — cegah duplicate
        // concurrent generate-stream (double AI cost + race pada saveArtifact).
        $st_ok = self::tryAcquireLock((int) $version->id, $stage);
        if (! $st_ok) {
            abort(409, 'Stage sedang diproses pipeline lain. Tunggu sebentar, lalu coba lagi.');
        }

        $pipeline = new PipelineRunner($version, $client);

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        ob_implicit_flush(true);

        return response()->stream(
            function () use ($pipeline, $stage, $auto, $lite, $versionId) {
                try {
                    $pipeline->run($stage, $auto, $lite);
                } finally {
                    self::releaseLock((int) $versionId, $stage);
                }
            },
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }

    public static function tryAcquireLock(int $versionId, string $stage): bool
    {
        $obj = crc32($stage) >= 2147483648 ? crc32($stage) - 4294967296 : crc32($stage);
        $result = DB::select('SELECT pg_try_advisory_lock(?, ?) AS locked', [$versionId, $obj]);

        return ! empty($result) && ($result[0]->locked === true || $result[0]->locked === 't' || $result[0]->locked === '1' || $result[0]->locked === 1);
    }

    public static function releaseLock(int $versionId, string $stage): void
    {
        $obj = crc32($stage) >= 2147483648 ? crc32($stage) - 4294967296 : crc32($stage);
        DB::select('SELECT pg_advisory_unlock(?, ?)', [$versionId, $obj]);
    }
}
