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
        $perPage = min((int) $request->query('per_page', 50), 100);

        $query = $request->user()->projects()
            ->with(['versions' => fn($q) => $q->latest()->limit(1)])
            ->withCount('versions');

        if ($q = $request->query('q')) {
            $query->where(function ($qry) use ($q) {
                $qry->where('title', 'ilike', "%{$q}%")
                    ->orWhere('idea', 'ilike', "%{$q}%");
            });
        }

        if ($request->boolean('favorite')) {
            $query->where('is_favorite', true);
        }

        $projects = $query->latest()->paginate($perPage);

        $projects->getCollection()->each(function ($project) {
            $latest = $project->versions->first();
            if ($latest && $latest->stage_status) {
                $done = collect($latest->stage_status)->filter(fn($s) => $s === 'done')->count();
                $project->setAttribute('progress', $done);
                $project->setAttribute('stage_status', $latest->stage_status);
                $project->setAttribute('latest_version_id', $latest->id);
            } else {
                $project->setAttribute('progress', 0);
                $project->setAttribute('stage_status', null);
                $project->setAttribute('latest_version_id', null);
            }
            unset($project->versions);
        });

        return response()->json($projects);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'idea' => ['required', 'string'],
            'target' => ['required', 'in:web,both'],
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

    public function update(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'idea' => ['sometimes', 'string'],
            'target' => ['sometimes', 'in:web,both'],
        ]);

        $project->update($data);

        return response()->json($project);
    }

    public function dashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $totalProjects = $user->projects()->count();
        $totalVersions = Version::whereHas('project', fn($q) => $q->where('user_id', $user->id))->count();
        $activeProjects = $user->projects()->whereHas('versions')->count();

        $today = now()->startOfDay();
        $projectsThisWeek = $user->projects()->where('created_at', '>=', $today->copy()->subDays(7))->count();
        $versionsThisWeek = Version::whereHas('project', fn($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', $today->copy()->subDays(7))->count();

        $favoriteProjects = $user->projects()->where('is_favorite', true)->count();

        $latestProjects = $user->projects()
            ->withCount('versions')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn($p) => [
                'id' => $p->id,
                'title' => $p->title,
                'target' => $p->target,
                'idea' => $p->idea,
                'versions_count' => $p->versions_count,
                'is_favorite' => $p->is_favorite,
                'updated_at' => $p->updated_at,
            ]);

        $recentActivities = \App\Models\Activity::whereHas('project', fn($q) => $q->where('user_id', $user->id))
            ->with('user:id,name')
            ->latest()
            ->take(5)
            ->get()
            ->toArray();

        return response()->json([
            'total_projects' => $totalProjects,
            'total_versions' => $totalVersions,
            'active_projects' => $activeProjects,
            'favorite_projects' => $favoriteProjects,
            'projects_this_week' => $projectsThisWeek,
            'versions_this_week' => $versionsThisWeek,
            'recent_projects' => $latestProjects,
            'recent_activities' => $recentActivities,
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        $project->delete();

        return response()->json(null, 204);
    }

    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        $project->update(['is_favorite' => !$project->is_favorite]);

        return response()->json(['is_favorite' => $project->fresh()->is_favorite]);
    }
}
