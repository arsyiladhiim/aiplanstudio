<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiProvider;
use App\Models\ResearchAgentSettings;
use App\Models\ResearchIdea;
use App\Services\Research\ResearchAgentService;
use App\Services\Research\WebSearchClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResearchAgentController extends Controller
{
    public function ideas(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $query = ResearchIdea::query()->latest('id');
        if ($date) {
            $query->where('window_date', $date);
        }

        $ideas = $query->limit(30)->get()->map(fn (ResearchIdea $i) => [
            'id' => $i->id,
            'window_date' => $i->window_date->toDateString(),
            'title' => $i->title,
            'target_users' => $i->target_users,
            'problem' => $i->problem,
            'solution' => $i->solution,
            'sources' => $i->sources ?? [],
            'created_at' => $i->created_at,
        ]);

        $settings = ResearchAgentSettings::singleton();
        $window = ResearchIdea::currentWindowDate(now());

        return response()->json([
            'ideas' => $ideas,
            'window_date' => $window,
            'count_today' => ResearchIdea::countForWindow($window),
            'max_per_day' => $settings->max_per_day,
        ]);
    }

    public function showSettings(): JsonResponse
    {
        $s = ResearchAgentSettings::singleton();

        return response()->json([
            'enabled' => $s->enabled,
            'search_provider' => $s->search_provider,
            'search_api_key_masked' => $s->maskedSearchKey(),
            'ai_provider_id' => $s->ai_provider_id,
            'max_per_day' => $s->max_per_day,
            'last_run_at' => $s->last_run_at?->diffForHumans(),
            'last_run_status' => $s->last_run_status,
        ]);
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'enabled' => ['sometimes', 'boolean'],
            'search_provider' => ['sometimes', 'in:tavily,brave'],
            'search_api_key' => ['nullable', 'string', 'max:200'],
            'ai_provider_id' => ['nullable', 'integer', 'exists:ai_providers,id'],
            'max_per_day' => ['sometimes', 'integer', 'min:1', 'max:50'],
        ]);

        $s = ResearchAgentSettings::singleton();
        if (array_key_exists('search_api_key', $data) && $data['search_api_key'] !== '' && $data['search_api_key'] !== null) {
            $s->search_api_key = $data['search_api_key'];
        }
        unset($data['search_api_key']);
        $s->fill($data)->save();

        return response()->json(['message' => 'Pengaturan research agent tersimpan.']);
    }

    public function aiProviders(): JsonResponse
    {
        return response()->json(
            AiProvider::query()->get(['id', 'name', 'model', 'provider_type'])
        );
    }

    public function testSearch(Request $request): JsonResponse
    {
        $data = $request->validate(['query' => ['nullable', 'string', 'max:200']]);
        $s = ResearchAgentSettings::singleton();

        try {
            $results = (new WebSearchClient($s->search_provider, $s->decryptedSearchKey()))
                ->search(($data['query'] ?? '') ?: 'digitalisasi UMKM Indonesia', 3);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => count($results).' hasil ditemukan.', 'results' => $results]);
    }

    public function runNow(ResearchAgentService $service): JsonResponse
    {
        $result = $service->collect(force: true);

        return response()->json($result);
    }
}
