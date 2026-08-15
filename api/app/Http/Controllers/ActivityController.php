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

        // CP-18.F3: optional filters (action, user_id, date range).
        $query = Activity::with('user:id,name')->with('project:id,title')->latest();

        if ($action = $request->query('action')) {
            $query->where('action', $action);
        }
        if ($userId = $request->query('user_id')) {
            $query->where('user_id', (int) $userId);
        }
        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        return response()->json($query->paginate($perPage));
    }
}
