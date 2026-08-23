<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\Gates\ArchGate;
use App\Services\Gates\DeployGate;
use App\Services\Gates\DiscoveryGate;
use App\Services\Gates\ProductionReadinessGate;
use App\Services\Gates\ReviewGate;
use App\Services\Gates\SecurityGate;
use App\Services\Gates\SmokeTestGate;
use App\Services\Gates\SpecGate;
use App\Services\StageGateRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageGateRegistryTest extends TestCase
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
        $this->project = Project::factory()->create([
            'user_id' => $this->user->id,
            'target' => 'both',
        ]);
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'stage_status' => Version::defaultStageStatus(),
        ]);
        $this->registry = new StageGateRegistry;
    }

    public function test_registry_memiliki_8_gate_classes(): void
    {
        $names = array_map(fn ($g) => $g->name(), $this->registry->gates());
        $this->assertContains($names[0] === DiscoveryGate::class || str_contains($names[0], 'DiscoveryGate') ? 'DiscoveryGate' : 'x', $names);
        $expected = ['DiscoveryGate', 'SpecGate', 'ArchGate', 'SecurityGate', 'DeployGate', 'ReviewGate', 'SmokeTestGate', 'ProductionReadinessGate'];
        foreach ($expected as $g) {
            $this->assertContains($g, $names, "Gate {$g} harus ada di registry.");
        }
    }

    public function test_gate_map_terisi_untuk_known_stages(): void
    {
        $map = $this->registry->gateMap();
        $this->assertSame('DiscoveryGate', $map['pertanyaan']);
        $this->assertSame('DiscoveryGate', $map['pertanyaan_mobile']);
        $this->assertSame('SpecGate', $map['analisa']);
        $this->assertSame('ArchGate', $map['architecture']);
        $this->assertSame('SecurityGate', $map['security']);
        $this->assertSame('DeployGate', $map['agents']);
        $this->assertSame('ReviewGate', $map['verify.review']);
        $this->assertSame('SmokeTestGate', $map['smoke_test']);
        $this->assertSame('ProductionReadinessGate', $map['verify.production_readiness']);
    }

    public function test_discovery_gate_pertanyaan_mobile_memerlukan_master_web_done(): void
    {
        // Tanpa master_web done
        $r = $this->registry->check($this->version, 'pertanyaan_mobile');
        $this->assertFalse($r['passes']);
        $this->assertSame('DiscoveryGate', $r['gate']);
        $this->assertStringContainsString('master_web', $r['reason']);

        // master_web done → pass
        $statuses = $this->version->stage_status;
        $statuses['master_web'] = 'done';
        $this->version->stage_status = $statuses;
        $this->version->save();

        $r = $this->registry->check($this->version, 'pertanyaan_mobile');
        $this->assertTrue($r['passes']);
    }

    public function test_spec_gate_analisa_memerlukan_pertanyaan_done(): void
    {
        $r = $this->registry->check($this->version, 'analisa');
        $this->assertFalse($r['passes']);
        $this->assertSame('SpecGate', $r['gate']);

        $statuses = $this->version->stage_status;
        $statuses['pertanyaan'] = 'done';
        $this->version->stage_status = $statuses;
        $this->version->save();

        $r = $this->registry->check($this->version, 'analisa');
        $this->assertTrue($r['passes']);
    }

    public function test_arch_gate_api_contract_memerlukan_erd_dan_architecture_done(): void
    {
        $r = $this->registry->check($this->version, 'api_contract');
        $this->assertFalse($r['passes']);

        $statuses = $this->version->stage_status;
        $statuses['erd'] = 'done';
        $statuses['architecture'] = 'done';
        $this->version->stage_status = $statuses;
        $this->version->save();

        $r = $this->registry->check($this->version, 'api_contract');
        $this->assertTrue($r['passes']);
    }

    public function test_security_gate_memerlukan_3_prerequisite(): void
    {
        $r = $this->registry->check($this->version, 'security');
        $this->assertFalse($r['passes']);

        $statuses = $this->version->stage_status;
        $statuses['env_config'] = 'done';
        $statuses['api_contract'] = 'done';
        $statuses['prd'] = 'done';
        $this->version->stage_status = $statuses;
        $this->version->save();

        $r = $this->registry->check($this->version, 'security');
        $this->assertTrue($r['passes']);
    }

    public function test_deploy_gate_memperlakukan_skipped_sebagai_satisfied(): void
    {
        $this->project->update(['target' => 'web']);
        $this->version->refresh();

        $statuses = $this->version->stage_status;
        $statuses['api_contract'] = 'done';
        $statuses['master_web'] = 'done';
        // master_mobile skipped karena project=web (PipelineRunner mobile-gate).
        $statuses['master_mobile'] = 'skipped';
        $this->version->stage_status = $statuses;
        $this->version->save();

        $deployGate = new DeployGate;
        $this->assertTrue($deployGate->passes($this->version, 'env_config'));
    }

    public function test_verify_gates_evidence_missing_means_blocked(): void
    {
        $r = $this->registry->check($this->version, 'verify.review');
        // Tanpa evidence table, fallback pass. Bila tabel ada dan belum ada row → blocked.
        $hasTable = \Schema::hasTable('version_stage_evidence');
        if ($hasTable) {
            $this->assertFalse($r['passes']);
        } else {
            $this->assertTrue($r['passes']);
        }
    }

    public function test_production_readiness_gate_default_window_7_hari(): void
    {
        $g = new ProductionReadinessGate;
        $this->assertSame(7, ProductionReadinessGate::WINDOW_DAYS);
        $this->assertSame('ProductionReadinessGate', $g->name());
    }

    public function test_check_return_passes_true_untuk_stage_tanpa_gate(): void
    {
        // 'pertanyaan' sebenarnya ada gate DiscoveryGate; test ini untuk stage non-gated.
        // Kita inject gate kosong.
        $empty = new StageGateRegistry([]);
        $r = $empty->check($this->version, 'pertanyaan');
        $this->assertTrue($r['passes']);
        $this->assertNull($r['gate']);
    }

    public function test_assert_menyimpan_ke_gate_states(): void
    {
        $this->registry->assert($this->version, 'analisa');
        $this->version->refresh();
        $states = $this->version->gate_states;
        $this->assertArrayHasKey('analisa', $states);
        $this->assertSame('SpecGate', $states['analisa']['gate']);
        $this->assertFalse($states['analisa']['passed']);
        $this->assertArrayHasKey('checked_at', $states['analisa']);
    }

    public function test_gate_pertanyaan_unconditional_pass_untuk_web_target(): void
    {
        $this->project->update(['target' => 'web']);
        $this->version->refresh();
        $r = $this->registry->check($this->version, 'pertanyaan');
        $this->assertTrue($r['passes']);
    }

    public function test_individual_gate_classes_terbentuk(): void
    {
        // Smoke instantiate setiap class.
        $classes = [DiscoveryGate::class, SpecGate::class, ArchGate::class, SecurityGate::class, DeployGate::class, ReviewGate::class, SmokeTestGate::class, ProductionReadinessGate::class];
        foreach ($classes as $cls) {
            $g = new $cls;
            $this->assertIsString($g->name());
            $this->assertIsArray($g->appliesTo());
            $this->assertIsString($g->reason($this->version, $g->appliesTo()[0]));
        }
    }
}
