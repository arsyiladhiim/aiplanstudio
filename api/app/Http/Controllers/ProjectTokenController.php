<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectTokenController extends Controller
{
    public function autoTrackingForVersion(Request $request, int $projectId, int $versionId): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($projectId);
        $version = Version::where('project_id', $project->id)->findOrFail($versionId);

        $tokenName = 'auto-tracking-'.substr(md5((string) $version->id), 0, 8);

        $existing = ProjectApiToken::where('project_id', $project->id)
            ->where('name', $tokenName)
            ->latest()
            ->first();

        if ($existing) {
            return response()->json([
                'id' => $existing->id,
                'name' => $existing->name,
                'token' => null,
                'secret' => null,
                'existing' => true,
                'message' => 'Token sudah ada. Buat token baru lewat /projects/{id}/tokens jika secret hilang.',
            ]);
        }

        $result = ProjectApiToken::generate($project, $tokenName);

        return response()->json([
            'id' => $result['model']->id,
            'name' => $result['model']->name,
            'token' => $result['token'],
            'secret' => $result['secret'],
            'existing' => false,
            'message' => 'Token baru dibuat. Salin secret SEKARANG — tidak akan ditampilkan lagi.',
        ], 201);
    }
}
