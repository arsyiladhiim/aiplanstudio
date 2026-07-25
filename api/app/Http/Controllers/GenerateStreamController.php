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

        if (!$versionId || !$stage) {
            abort(422, 'Parameter "version" dan "stage" wajib diisi.');
        }

        $validStages = ['analisa', 'prd', 'architecture', 'erd', 'phases', 'master'];
        if (!in_array($stage, $validStages)) {
            abort(422, 'Stage tidak valid. Pilih: ' . implode(', ', $validStages));
        }

        $version = Version::whereHas('project', fn($q) => $q->where('user_id', $request->user()->id))
            ->findOrFail($versionId);

        $pipeline = new PipelineRunner($version, $client);

        return response()->stream(
            fn() => $pipeline->run($stage, $auto),
            200,
            [
                'Content-Type' => 'text/event-stream',
                'Cache-Control' => 'no-cache',
                'Connection' => 'keep-alive',
                'X-Accel-Buffering' => 'no',
            ]
        );
    }
}
