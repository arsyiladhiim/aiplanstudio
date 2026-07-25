<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $projects = $request->user()->projects()
            ->withCount('versions')
            ->latest()
            ->get();

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'idea' => ['required', 'string'],
            'target' => ['required', 'in:web,mobile,both'],
            'stack' => ['nullable', 'string', 'max:255'],
        ]);

        $project = $request->user()->projects()->create($data);
        // Buat versi 1 otomatis dengan stage_status default
        $project->versions()->create([
            'version_no' => 1,
            'stage_status' => Version::defaultStageStatus(),
        ]);

        return response()->json($project, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)
            ->with(['versions' => fn($q) => $q->latest()])
            ->findOrFail($id);

        return response()->json($project);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        $project->delete();

        return response()->json(null, 204);
    }
}
