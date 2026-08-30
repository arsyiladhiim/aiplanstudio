<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Project;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 50), 100);

        $query = $request->user()->projects()
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_no')->limit(1)])
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

        if (in_array($target = $request->query('target', ''), ['web', 'both'], true)) {
            $query->where('target', $target);
        }

        if ($request->boolean('pinned')) {
            $query->where('is_pinned', true);
        }

        if ($request->boolean('archived')) {
            $query->whereNotNull('archived_at');
        } else {
            $query->whereNull('archived_at');
        }

        $projects = $query
            ->orderByDesc('is_pinned')
            ->orderByDesc('is_favorite')
            ->latest()
            ->paginate($perPage);

        $projects->getCollection()->each(function ($project) {
            $latest = $project->versions->first();
            if ($latest && $latest->stage_status) {
                $project->setAttribute('progress', $latest->progressCount());
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
            'idea' => ['required', 'string', 'max:5000'],
            'target' => ['required', 'in:web,both'],
            'stack' => ['nullable', 'string', 'max:255'],
        ]);

        // B-M3: reject ideas containing obvious prompt-injection role markers
        // di awal/awal baris (e.g. "system:\n...", "### Instruksi:" terlalu panjang).
        if (preg_match('/^\s*(system|assistant|user)\s*:/i', $data['idea'])) {
            return response()->json([
                'message' => 'Idea mengandung format yang tidak diperbolehkan (role markers).',
            ], 422);
        }

        $project = DB::transaction(function () use ($request, $data) {
            $project = $request->user()->projects()->create($data);
            $project->versions()->create([
                'version_no' => 1,
                'stage_status' => Version::defaultStageStatus(),
            ]);
            Activity::create([
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'action' => Activity::ACTION_CREATED_PROJECT,
                'description' => "Project \"{$project->title}\" dibuat",
            ]);

            return $project;
        });

        return response()->json($project, 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)
            ->with(['versions' => function ($q) {
                $q->orderByDesc('version_no')->limit(10)->with('phaseProgress');
            }])
            ->findOrFail($id);

        return response()->json($project);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        $this->authorize('update', $project);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'idea' => ['sometimes', 'string', 'max:5000'],
            'target' => ['sometimes', 'in:web,both'],
            'stack' => ['sometimes', 'nullable', 'string', 'max:255'],
        ]);

        $project->update($data);

        Activity::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'action' => Activity::ACTION_UPDATED_PROJECT,
            'description' => "Project \"{$project->title}\" diperbarui",
            'metadata' => $data,
        ]);

        return response()->json($project);
    }

    public function dashboardStats(Request $request): JsonResponse
    {
        $user = $request->user();
        $totalProjects = $user->projects()->count();
        $totalVersions = Version::whereHas('project', fn ($q) => $q->where('user_id', $user->id))->count();
        $activeProjects = $user->projects()
            ->whereHas('versions', function ($q) {
                $q->whereRaw("EXISTS (SELECT 1 FROM jsonb_each_text(stage_status) kv WHERE kv.value = 'done')");
            })
            ->count();

        $today = now()->startOfDay();
        $projectsThisWeek = $user->projects()->where('created_at', '>=', $today->copy()->subDays(7))->count();
        $versionsThisWeek = Version::whereHas('project', fn ($q) => $q->where('user_id', $user->id))
            ->where('created_at', '>=', $today->copy()->subDays(7))->count();

        $favoriteProjects = $user->projects()->where('is_favorite', true)->count();

        $latestProjects = $user->projects()
            ->withCount('versions')
            ->with(['versions' => fn ($q) => $q->orderByDesc('version_no')->limit(1)])
            ->latest()
            ->take(5)
            ->get()
            ->map(function ($p) {
                $latest = $p->versions->first();
                $progress = 0;
                $stageCount = 0;
                $originality = null;
                if ($latest && $latest->stage_status) {
                    $progress = $latest->progressCount();
                    $stageCount = $latest->visibleStageCount();
                }
                if ($latest && $latest->stage_quality) {
                    $quality = collect($latest->stage_quality)->filter(fn ($q) => is_numeric($q));
                    if ($quality->isNotEmpty()) {
                        $originality = (int) round($quality->avg() * 100);
                    }
                }
                unset($p->versions);

                return [
                    'id' => $p->id,
                    'title' => $p->title,
                    'target' => $p->target,
                    'idea' => $p->idea,
                    'versions_count' => $p->versions_count,
                    'is_favorite' => $p->is_favorite,
                    'updated_at' => $p->updated_at,
                    'progress' => $progress,
                    'stage_count' => $stageCount,
                    'originality_score' => $originality,
                    'latest_version_id' => $latest->id ?? null,
                ];
            });

        $recentActivities = Activity::whereHas('project', fn ($q) => $q->where('user_id', $user->id))
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
        $this->authorize('delete', $project);
        Activity::create([
            'project_id' => $project->id,
            'user_id' => $request->user()->id,
            'action' => Activity::ACTION_DELETED_PROJECT,
            'description' => "Project \"{$project->title}\" dihapus",
        ]);
        $project->delete();

        return response()->json(null, 204);
    }

    public function toggleFavorite(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        $this->authorize('update', $project);
        $project->update(['is_favorite' => ! $project->is_favorite]);

        return response()->json(['is_favorite' => $project->fresh()->is_favorite]);
    }

    public function togglePin(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        $this->authorize('update', $project);
        $project->update(['is_pinned' => ! $project->is_pinned]);

        return response()->json(['is_pinned' => $project->fresh()->is_pinned]);
    }

    public function toggleArchive(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)->findOrFail($id);
        $this->authorize('update', $project);
        $project->update(['archived_at' => $project->archived_at ? null : now()]);

        return response()->json(['archived_at' => $project->fresh()->archived_at]);
    }

    public function tasks(Request $request, int $id): JsonResponse
    {
        $project = Project::where('user_id', $request->user()->id)
            ->findOrFail($id);

        $tasks = DB::table('aiplanstudio_project.task_progress')
            ->join('aiplanstudio_project.phase_progress', 'task_progress.phase_progress_id', '=', 'phase_progress.id')
            ->join('aiplanstudio_project.versions', 'phase_progress.version_id', '=', 'versions.id')
            ->where('versions.project_id', $project->id)
            ->orderByDesc('versions.version_no')
            ->orderBy('phase_progress.phase_key')
            ->orderBy('task_progress.task_key')
            ->select(
                'task_progress.id',
                'task_progress.task_key',
                'task_progress.task_type',
                'task_progress.title',
                'task_progress.status',
                'task_progress.checkpoint',
                'phase_progress.phase_key',
                'versions.version_no',
            )
            ->get();

        $summary = [
            'total' => $tasks->count(),
            'done' => $tasks->where('status', 'done')->count(),
            'running' => $tasks->where('status', 'running')->count(),
            'pending' => $tasks->where('status', 'pending')->count(),
            'error' => $tasks->where('status', 'error')->count(),
        ];

        return response()->json([
            'summary' => $summary,
            'tasks' => $tasks,
        ]);
    }

    public function exportAll(Request $request, int $id)
    {
        $project = Project::where('user_id', $request->user()->id)
            ->with(['versions' => fn ($q) => $q->orderBy('version_no')])
            ->findOrFail($id);

        if ($project->versions->isEmpty()) {
            return response()->json(['message' => 'Project belum memiliki versi.'], 422);
        }

        $projectTitle = Str::slug($project->title);
        $versions = $project->versions;

        return new StreamedResponse(function () use ($versions, $projectTitle) {
            $zip = new \ZipArchive;
            $tmpPath = tempnam(sys_get_temp_dir(), 'export_all').'.zip';
            $zip->open($tmpPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

            foreach ($versions as $version) {
                $content = (new VersionController)->buildMarkdownPublic($version);
                $zip->addFromString("{$projectTitle}-v{$version->version_no}.md", $content);
                $zip->addFromString("v{$version->version_no}/erd.json", json_encode($version->erd ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                if ($version->mobile_standards) {
                    $zip->addFromString("v{$version->version_no}/mobile-standards.md", $version->mobile_standards);
                }
                if ($version->mobile_agents) {
                    $zip->addFromString("v{$version->version_no}/mobile-agents.md", $version->mobile_agents);
                }
            }
            $zip->close();

            readfile($tmpPath);
            @unlink($tmpPath);
        }, 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => "attachment; filename=\"{$projectTitle}-all-versions.zip\"",
        ]);
    }

    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['projects' => [], 'versions' => []]);
        }

        $userId = $request->user()->id;

        $projects = $request->user()->projects()
            ->where(function ($qry) use ($q) {
                $qry->where('title', 'ilike', "%{$q}%")
                    ->orWhere('idea', 'ilike', "%{$q}%")
                    ->orWhere('stack', 'ilike', "%{$q}%");
            })
            ->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->limit(8)
            ->get(['id', 'title', 'target', 'is_pinned', 'is_favorite']);

        $versions = Version::query()
            ->select(['versions.id', 'versions.project_id', 'versions.version_no', 'versions.pertanyaan'])
            ->whereHas('project', fn ($p) => $p->where('user_id', $userId))
            ->where(function ($qry) use ($q) {
                $qry->where('pertanyaan', 'ilike', "%{$q}%")
                    ->orWhere('analysis', 'ilike', "%{$q}%")
                    ->orWhere('prd', 'ilike', "%{$q}%")
                    ->orWhere('architecture', 'ilike', "%{$q}%");
            })
            ->with('project:id,title,target')
            ->orderByDesc('versions.updated_at')
            ->limit(8)
            ->get();

        return response()->json([
            'projects' => $projects,
            'versions' => $versions,
        ]);
    }
}
