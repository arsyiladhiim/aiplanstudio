<?php

namespace Tests\Feature;

use App\Models\AiProvider;
use App\Models\ResearchAgentSettings;
use App\Models\ResearchIdea;
use App\Models\User;
use App\Services\Research\ResearchAgentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResearchAgentTest extends TestCase
{
    use RefreshDatabase;

    private function adminProvider(): AiProvider
    {
        return AiProvider::create([
            'name' => 'Test AI',
            'base_url' => 'https://api.openai.com/v1',
            'api_key' => 'sk-test',
            'model' => 'gpt-test',
            'provider_type' => 'openai',
            'is_active' => true,
        ]);
    }

    private function settings(array $overrides = []): ResearchAgentSettings
    {
        $s = ResearchAgentSettings::singleton();
        $s->fill(array_merge(['enabled' => true, 'search_provider' => 'tavily', 'max_per_day' => 5], $overrides));
        $s->search_api_key = 'tvly-test-key';

        return tap($s)->save();
    }

    private function fakeHttp(): void
    {
        $body = json_encode([['title' => 'Ide 1', 'target_users' => 'UMKM', 'problem' => 'P1', 'solution' => 'S1']]);
        Http::fake([
            'api.openai.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => 'umkm digitalization pain points']]]])
                ->push(['choices' => [['message' => ['content' => $body]]]]),
            'api.tavily.com/*' => Http::response(['results' => [
                ['title' => 'T1', 'url' => 'https://ex.com/1', 'content' => 'snip'],
            ]]),
        ]);
    }

    public function test_collect_creates_ideas(): void
    {
        $this->fakeHttp();
        $this->settings(['ai_provider_id' => $this->adminProvider()->id]);

        $result = (new ResearchAgentService)->collect();

        $this->assertSame(1, $result['created']);
        $this->assertDatabaseCount('aiplanstudio_settings.research_ideas', 1);
    }

    public function test_collect_skips_when_quota_full(): void
    {
        $window = ResearchIdea::currentWindowDate(now());
        foreach (range(1, 5) as $i) {
            ResearchIdea::create(['window_date' => $window, 'title' => "Ide {$i}", 'target_users' => 'x', 'problem' => 'p', 'solution' => 's']);
        }
        $this->settings(['ai_provider_id' => $this->adminProvider()->id]);
        Http::fake();

        $result = (new ResearchAgentService)->collect();

        $this->assertSame('quota_full', $result['status']);
    }

    public function test_collect_skips_when_not_configured(): void
    {
        $result = (new ResearchAgentService)->collect();
        $this->assertSame('not_configured', $result['status']);
    }

    public function test_member_cannot_access_research_endpoints(): void
    {
        $member = User::factory()->create(['role' => 'member']);
        $this->actingAs($member, 'sanctum')->getJson('/api/research/ideas')->assertStatus(403);
        $this->actingAs($member, 'sanctum')->patchJson('/api/research/settings', ['enabled' => true])->assertStatus(403);
    }

    public function test_admin_can_read_and_update_settings(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $provider = $this->adminProvider();

        $this->actingAs($admin, 'sanctum')->patchJson('/api/research/settings', [
            'enabled' => true,
            'search_provider' => 'brave',
            'search_api_key' => 'BSA-key-123',
            'ai_provider_id' => $provider->id,
            'max_per_day' => 8,
        ])->assertStatus(200);

        $json = $this->actingAs($admin, 'sanctum')->getJson('/api/research/settings')->assertStatus(200)->json();
        $this->assertTrue($json['enabled']);
        $this->assertSame('brave', $json['search_provider']);
        $this->assertSame('BSA••••••-123', $json['search_api_key_masked']);
        $this->assertSame(8, $json['max_per_day']);
    }

    public function test_admin_can_test_search(): void
    {
        Http::fake([
            'api.tavily.com/*' => Http::response(['results' => [
                ['title' => 'T1', 'url' => 'https://ex.com/1', 'content' => 'snip'],
            ]]),
        ]);
        $this->settings();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/research/test-search')
            ->assertStatus(200)
            ->assertJson(['ok' => true]);
    }
}
