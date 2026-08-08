<?php

namespace App\Http\Controllers;

use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GenerateStreamController extends Controller
{
    public function __invoke(Request $request, AiClient $client): StreamedResponse
    {
        $versionId = $request->query('version');
        $stage = $request->query('stage');
        $auto = $request->query('auto', '0') === '1';

        if (! $versionId || ! $stage) {
            abort(422, 'Parameter "version" dan "stage" wajib diisi.');
        }

        $validStages = [
            'pertanyaan', 'analisa', 'prd', 'architecture', 'erd',
            'standards_web', 'agents_web', 'phases_web', 'master_web',
            'phases_mobile', 'standards_mobile', 'agents_mobile', 'master_mobile',
        ];
        if (! in_array($stage, $validStages)) {
            abort(422, 'Stage tidak valid. Pilih: '.implode(', ', $validStages));
        }

        $version = Version::whereHas('project', fn ($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($versionId);

        $pipeline = new PipelineRunner($version, $client);

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        ob_implicit_flush(true);

        return response()->stream(
            fn () => $pipeline->run($stage, $auto),
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }
}
