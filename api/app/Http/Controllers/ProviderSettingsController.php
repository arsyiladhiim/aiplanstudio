<?php

namespace App\Http\Controllers;

use App\Models\AiProvider;
use App\Services\AiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProviderSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        $providers = AiProvider::latest()->get()->map(fn($p) => [
            'id' => $p->id,
            'name' => $p->name,
            'base_url' => $p->base_url,
            'model' => $p->model,
            'provider_type' => $p->provider_type,
            'is_active' => $p->is_active,
            'api_key_masked' => $p->maskedKey(),
            'last_test_response' => $p->last_test_response,
            'last_test_at' => $p->last_test_at?->diffForHumans(),
            'created_at' => $p->created_at,
        ]);
        return response()->json($providers);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'base_url' => ['required', 'url'],
            'api_key' => ['required', 'string'],
            'model' => ['required', 'string', 'max:100'],
            'provider_type' => ['required', 'in:openai,anthropic,custom'],
        ]);

        $provider = AiProvider::create($data);
        return response()->json(['id' => $provider->id, 'message' => 'Provider tersimpan.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $provider = AiProvider::findOrFail($id);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:100'],
            'base_url' => ['sometimes', 'url'],
            'api_key' => ['nullable', 'string'],
            'model' => ['sometimes', 'string', 'max:100'],
            'provider_type' => ['sometimes', 'in:openai,anthropic,custom'],
        ]);

        if (empty($data['api_key'])) unset($data['api_key']);
        $provider->update($data);

        return response()->json(['message' => 'Provider diperbarui.', 'api_key_masked' => $provider->maskedKey()]);
    }

    public function destroy(int $id): JsonResponse
    {
        $provider = AiProvider::findOrFail($id);
        $provider->delete();
        return response()->json(null, 204);
    }

    public function setActive(int $id): JsonResponse
    {
        AiProvider::query()->update(['is_active' => false]);
        $provider = AiProvider::findOrFail($id);
        $provider->update(['is_active' => true]);
        return response()->json(['message' => "{$provider->name} aktif secara global."]);
    }

    public function test(int $id): JsonResponse
    {
        $provider = AiProvider::findOrFail($id);
        $client = new AiClient($provider);
        $result = $client->testConnection();

        $provider->update([
            'last_test_response' => $result['message'],
            'last_test_at' => now(),
        ]);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function testPrompt(Request $request, int $id): JsonResponse
    {
        $provider = AiProvider::findOrFail($id);
        $data = $request->validate(['prompt' => ['required', 'string', 'max:500']]);

        $client = new AiClient($provider);
        $result = $client->testPrompt($data['prompt']);

        $provider->update([
            'last_test_response' => $result['response'] ?? $result['message'],
            'last_test_at' => now(),
        ]);

        return response()->json($result, $result['ok'] ? 200 : 422);
    }
}
