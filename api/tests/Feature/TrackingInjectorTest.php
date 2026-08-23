<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\User;
use App\Models\Version;
use App\Services\TrackingInjector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrackingInjectorTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    private TrackingInjector $injector;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'master_prompt' => "## 6. Tracking Webhook\n... baseline prompt tanpa marker ...",
        ]);

        // TrackingInjector memilih token via nama `auto-tracking-{md5($v->id)[0:8]}`.
        $autoName = 'auto-tracking-'.substr(md5((string) $this->version->id), 0, 8);
        ProjectApiToken::generate($this->project, $autoName);
        $this->version->refresh();

        $this->injector = new TrackingInjector;
    }

    public function test_inject_menambahkan_marker_dan_url_versi(): void
    {
        $out = $this->injector->inject($this->version, $this->version->master_prompt);

        $this->assertStringContainsString(TrackingInjector::MARKER_START, $out);
        $this->assertStringContainsString(TrackingInjector::MARKER_END, $out);
        $this->assertStringContainsString((string) $this->version->id, $out);
        $this->assertStringContainsString(config('app.tracking_base_url'), $out);
    }

    public function test_inject_menghasilkan_snippet_bash_python_node_siap_pakai(): void
    {
        $out = $this->injector->inject($this->version, $this->version->master_prompt);

        $this->assertStringContainsString('curl ', $out, 'Snippet bash harus berisi curl.');
        $this->assertStringContainsString('X POST ', $out, 'Snippet harus berisi HTTP POST.');
        $this->assertStringContainsString('import hashlib', $out, 'Snippet Python harus lengkap.');
        $this->assertStringContainsString('require(', $out, 'Snippet Node harus berisi require.');
        $this->assertStringContainsString('phase-complete', $out);
    }

    public function test_inject_idempotent_untuk_multi_call(): void
    {
        $first = $this->injector->inject($this->version, $this->version->master_prompt);
        $second = $this->injector->inject($this->version, $first);

        $this->assertSame(
            substr_count($first, TrackingInjector::MARKER_START),
            substr_count($second, TrackingInjector::MARKER_START),
            'Marker harus tepat satu walau di-inject berkali-kali.',
        );
    }

    public function test_inject_tanpa_token_masih_menghasilkan_marker_dengan_placeholder_aman(): void
    {
        ProjectApiToken::where('project_id', $this->project->id)->delete();
        $this->version->refresh();

        $out = $this->injector->inject($this->version, $this->version->master_prompt);

        $this->assertStringContainsString(TrackingInjector::MARKER_START, $out);
        $this->assertStringNotContainsString('Bearer ', $out, 'Tidak ada Authorization Bearer ketika token kosong.');
    }

    public function test_inject_tidak_mengubah_konten_non_tracking(): void
    {
        $baseline = $this->version->master_prompt;
        $out = $this->injector->inject($this->version, $baseline);

        $this->assertStringContainsString('## 6. Tracking Webhook', $out);
        $payload = json_decode(json_encode($this->injector->build($this->version)), true);
        $this->assertArrayHasKey('has_token', $payload);
        $this->assertIsBool($payload['has_token']);
    }
}
