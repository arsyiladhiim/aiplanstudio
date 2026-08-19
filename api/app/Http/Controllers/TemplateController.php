<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Template;
use App\Models\Version;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Template::whereNull('user_id')
            ->orWhere('user_id', auth()->id())
            ->orderBy('name')
            ->paginate(50)->items());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'target' => ['required', 'in:web,both'],
            'description' => ['nullable', 'string', 'max:2000'],
            'seed' => ['nullable', 'array'],
        ]);

        return response()->json(Template::create($data), 201);
    }

    public function show(int $id): JsonResponse
    {
        $template = Template::whereNull('user_id')->orWhere('user_id', auth()->id())->find($id);
        if (! $template) {
            return response()->json(['message' => 'Template tidak ditemukan.'], 404);
        }

        return response()->json($template);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $template = Template::find($id);
        if (! $template) {
            return response()->json(['message' => 'Template tidak ditemukan.'], 404);
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'target' => ['sometimes', 'in:web,both'],
            'description' => ['nullable', 'string', 'max:2000'],
            'seed' => ['nullable', 'array'],
        ]);

        $template->fill($data);
        $template->save();

        return response()->json($template);
    }

    public function destroy(int $id): JsonResponse
    {
        $template = Template::find($id);
        if (! $template) {
            return response()->json(['message' => 'Template tidak ditemukan.'], 404);
        }
        $template->delete();

        return response()->json(null, 204);
    }

    public function instantiate(Request $request, int $id): JsonResponse
    {
        $template = Template::find($id);
        if (! $template) {
            return response()->json(['message' => 'Template tidak ditemukan.'], 404);
        }

        $override = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'idea' => ['nullable', 'string', 'max:5000'],
            'stack' => ['nullable', 'string', 'max:255'],
            'target' => ['nullable', 'in:web,both'],
        ]);

        $seed = is_array($template->seed) ? $template->seed : [];

        $data = [
            'title' => $override['title'] ?? $template->name,
            'idea' => $override['idea'] ?? ($seed['idea'] ?? ''),
            'target' => $override['target'] ?? $template->target,
            'stack' => $override['stack'] ?? ($seed['stack'] ?? null),
        ];

        if ($data['idea'] === '') {
            return response()->json(['message' => 'Idea kosong. Template tidak memiliki seed.idea; isi lewat parameter idea.'], 422);
        }

        $project = DB::transaction(function () use ($request, $data, $template) {
            $project = $request->user()->projects()->create($data);
            $project->versions()->create([
                'version_no' => 1,
                'stage_status' => Version::defaultStageStatus(),
            ]);
            Activity::create([
                'project_id' => $project->id,
                'user_id' => $request->user()->id,
                'action' => Activity::ACTION_CREATED_PROJECT,
                'description' => "Project \"{$project->title}\" dibuat dari template \"{$template->name}\"",
                'metadata' => ['template_id' => $template->id],
            ]);

            return $project;
        });

        return response()->json($project, 201);
    }
}
