<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Models\VersionStageEvidence;
use App\Services\StageGateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompositeGateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    private StageGateRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id, 'target' => 'both']);
        $this->version = Version::factory()->create(['project_id' => $this->project->id]);
        $this->registry = new StageGateRegistry;
    }

    public function test_verify_review_gate_blocked_tanpa_evidence(): void
    {
        $r = $this->registry->check($this->version, 'verify.review');
        $this->assertFalse($r['passes']);
        $this->assertSame('ReviewGate', $r['gate']);
        $this->assertStringContainsString('evidence', strtolower($r['reason']));
    }

    public function test_verify_review_gate_pass_dengan_evidence_lengkap(): void
    {
        VersionStageEvidence::create([
            'project_id' => $this->project->id,
            'version_id' => $this->version->id,
            'stage_key' => 'verify.review',
            'security_passed' => true,
            'perf_passed' => true,
        ]);

        $r = $this->registry->check($this->version, 'verify.review');
        $this->assertTrue($r['passes']);
    }

    public function test_verify_review_gate_blocked_jika_security_false(): void
    {
        VersionStageEvidence::create([
            'project_id' => $this->project->id,
            'version_id' => $this->version->id,
            'stage_key' => 'verify.review',
            'security_passed' => false,
            'perf_passed' => true,
        ]);

        $r = $this->registry->check($this->version, 'verify.review');
        $this->assertFalse($r['passes']);
        $this->assertStringContainsString('security', strtolower($r['reason']));
    }

    public function test_smoke_test_gate_pass_dengan_tests_dan_build(): void
    {
        VersionStageEvidence::create([
            'project_id' => $this->project->id,
            'version_id' => $this->version->id,
            'stage_key' => 'smoke_test',
            'tests_passed' => true,
            'build_passed' => true,
        ]);

        $r = $this->registry->check($this->version, 'smoke_test');
        $this->assertTrue($r['passes']);
    }

    public function test_smoke_test_gate_blocked_tanpa_tests(): void
    {
        VersionStageEvidence::create([
            'project_id' => $this->project->id,
            'version_id' => $this->version->id,
            'stage_key' => 'smoke_test',
            'tests_passed' => false,
            'build_passed' => true,
        ]);

        $r = $this->registry->check($this->version, 'smoke_test');
        $this->assertFalse($r['passes']);
    }

    public function test_production_readiness_gate_blocked_evidence_kurang(): void
    {
        // Hanya 1 evidence (verify.review), production readiness butuh 3.
        VersionStageEvidence::create([
            'project_id' => $this->project->id,
            'version_id' => $this->version->id,
            'stage_key' => 'verify.review',
            'security_passed' => true,
            'perf_passed' => true,
        ]);

        $r = $this->registry->check($this->version, 'verify.production_readiness');
        $this->assertFalse($r['passes']);
        $this->assertSame('ProductionReadinessGate', $r['gate']);
    }

    public function test_production_readiness_gate_pass_semua_evidence_lengkap_dan_window_7_hari(): void
    {
        $evidence = [
            'tests_passed' => true,
            'lint_passed' => true,
            'build_passed' => true,
            'migrate_passed' => true,
            'security_passed' => true,
            'perf_passed' => true,
        ];
        foreach (['verify.review', 'smoke_test', 'verify.production_readiness'] as $stage) {
            VersionStageEvidence::create([
                'project_id' => $this->project->id,
                'version_id' => $this->version->id,
                'stage_key' => $stage,
            ] + $evidence);
        }

        $r = $this->registry->check($this->version, 'verify.production_readiness');
        $this->assertTrue($r['passes']);
    }

    public function test_production_readiness_gate_blocked_evidence_di_luar_window_7_hari(): void
    {
        $evidence = [
            'tests_passed' => true,
            'lint_passed' => true,
            'build_passed' => true,
            'migrate_passed' => true,
            'security_passed' => true,
            'perf_passed' => true,
        ];
        foreach (['verify.review', 'smoke_test', 'verify.production_readiness'] as $stage) {
            $row = VersionStageEvidence::create([
                'project_id' => $this->project->id,
                'version_id' => $this->version->id,
                'stage_key' => $stage,
            ] + $evidence);
            // Backdate ke 8 hari lalu.
            $row->updated_at = now()->subDays(8);
            $row->created_at = now()->subDays(8);
            $row->save();
        }

        $r = $this->registry->check($this->version, 'verify.production_readiness');
        $this->assertFalse($r['passes']);
        $this->assertStringContainsString('7', $r['reason']);
    }
}
