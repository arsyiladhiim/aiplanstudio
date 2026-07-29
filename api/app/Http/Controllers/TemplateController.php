<?php

namespace App\Http\Controllers;

use App\Models\Template;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Template::paginate(50)->items());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'target' => ['required', 'in:web,mobile,both'],
            'description' => ['nullable', 'string'],
            'seed' => ['nullable', 'array'],
        ]);

        return response()->json(Template::create($data), 201);
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
}
