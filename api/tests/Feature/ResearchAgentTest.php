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

    private function seedIdeas(int $count, string $window): void
    {
        foreach (range(1, $count) as $i) {
            ResearchIdea::create([
                'window_date' => $window,
                'title' => "Ide Seed {$i} {$window}",
                'target_users' => 'UMKM',
                'problem' => "problem {$i}",
                'solution' => "solution {$i}",
            ]);
        }
    }

    public function test_ideas_search_by_keyword(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        ResearchIdea::create(['window_date' => '2026-08-20', 'title' => 'Logistik Dingin', 'target_users' => 'Petani', 'problem' => 'rantai dingin buruk', 'solution' => 'IoT monitor']);
        ResearchIdea::create(['window_date' => '2026-08-20', 'title' => 'Kasir Digital', 'target_users' => 'Warung', 'problem' => 'pencatatan manual', 'solution' => 'POS app']);

        $json = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/research/ideas?q=logistik')
            ->assertStatus(200)->json();

        $this->assertCount(1, $json['ideas']);
        $this->assertSame('Logistik Dingin', $json['ideas'][0]['title']);
    }

    public function test_ideas_date_range_filter(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedIdeas(1, '2026-08-18');
        $this->seedIdeas(1, '2026-08-20');

        $json = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/research/ideas?date_from=2026-08-19&date_to=2026-08-21')
            ->assertStatus(200)->json();

        $this->assertCount(1, $json['ideas']);
        $this->assertSame('2026-08-20', $json['ideas'][0]['window_date']);
    }

    public function test_ideas_pagination(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedIdeas(25, '2026-08-20');

        $json = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/research/ideas?page=2')
            ->assertStatus(200)->json();

        $this->assertSame(2, $json['pagination']['current_page']);
        $this->assertSame(2, $json['pagination']['last_page']);
        $this->assertSame(25, $json['pagination']['total']);
        $this->assertCount(5, $json['ideas']);
    }

    public function test_ideas_default_response_backward_compatible(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->seedIdeas(2, '2026-08-20');

        $json = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/research/ideas')
            ->assertStatus(200)->json();

        $this->assertArrayHasKey('count_today', $json);
        $this->assertArrayHasKey('max_per_day', $json);
        $this->assertArrayNotHasKey('pagination', $json);
    }
}
