<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class ActivityController extends Controller
{
    public function index(Request $request, Project $project): JsonResponse
    {
        abort_unless($project->user_id === $request->user()->id, 404);

        $perPage = min((int) $request->query('per_page', 50), 100);

        $activities = $project->activities()
            ->with('user:id,name')
            ->paginate($perPage);

        return response()->json($activities);
    }

    public function globalIndex(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 100);
        $activities = Activity::with('user:id,name')
            ->with('project:id,title')
            ->latest()
            ->paginate($perPage);
        return response()->json($activities);
    }
}
