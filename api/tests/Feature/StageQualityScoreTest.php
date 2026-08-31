<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageQualityScoreTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->version = Version::factory()->create([
            'project_id' => $this->project->id,
            'stage_status' => Version::defaultStageStatus(),
        ]);
    }

    public function test_quality_score_stored_after_save_artifact(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $content = "# Analisa\n\n## 1. Intent Summary\nRingkasan\n\n## 2. User Personas\nPersona\n\n## 3. Core Problem\nMasalah\n\n## 4. Success Metrics\nMetrik\n\n## 5. Anti-Goals\nTidak\n\n## 6. Daftar Halaman\n- Dashboard\n\n".str_repeat('Analisa detail lebih panjang untuk mencapai minimal panjang token. ', 60);

        $ref->invoke($runner, 'analisa', $content);
        $this->version->refresh();

        $quality = $this->version->stage_quality;
        $this->assertIsArray($quality);
        $this->assertArrayHasKey('analisa', $quality);
        $this->assertGreaterThanOrEqual(0.4, $quality['analisa']);
        $this->assertLessThanOrEqual(1.0, $quality['analisa']);
    }

    public function test_quality_score_low_for_generic_content(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'computeStageQuality');
        $ref->setAccessible(true);

        $content = 'Lorem ipsum dolor sit amet. Short.';
        $score = $ref->invoke($runner, 'prd', $content);
        $this->assertLessThan(0.6, $score);
    }

    public function test_quality_score_high_for_complete_original_content(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'computeStageQuality');
        $ref->setAccessible(true);

        $content = "# PRD\n\n## 1. Overview\nSpesifik untuk warung\n## 2. User Stories\nuser story acceptance\n## 3. Functional Requirements\nfunctional requirement\n## 4. Non-Functional\n## 5. Out of Scope\n## 6. Assumptions\n## 7. Differentiation\n## 8. Open Questions\n\n".str_repeat('Dokumen spesifik dan orisinal untuk produk lokal. ', 80);

        $score = $ref->invoke($runner, 'prd', $content);
        $this->assertGreaterThanOrEqual(0.7, $score);
    }

    public function test_quality_score_erd_json_rubric(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'computeStageQuality');
        $ref->setAccessible(true);

        // 8 tabel lengkap + 8 relasi → harus tinggi (>0.7), bukan 0.4 saja.
        $nodes = [];
        $edges = [];
        for ($i = 1; $i <= 8; $i++) {
            $nodes[] = ['id' => "t{$i}", 'label' => "t{$i}", 'fields' => ['id', 'name', 'created_at']];
            $edges[] = ['from' => 't1', 'to' => "t{$i}", 'relation' => 'one-to-many'];
        }
        $content = json_encode(['nodes' => $nodes, 'edges' => $edges]);
        $score = $ref->invoke($runner, 'erd', $content);
        $this->assertGreaterThan(0.7, $score);

        // ERD kecil 2 tabel → rendah tapi terukur (< 0.7), tetap valid.
        $small = json_encode([
            'nodes' => [['id' => 'a', 'label' => 'a', 'fields' => ['id']], ['id' => 'b', 'label' => 'b', 'fields' => ['id']]],
            'edges' => [['from' => 'a', 'to' => 'b', 'relation' => 'one-to-one']],
        ]);
        $scoreSmall = $ref->invoke($runner, 'erd', $small);
        $this->assertLessThan($score, $scoreSmall);
        $this->assertGreaterThanOrEqual(0.4, $scoreSmall);

        // Bukan JSON → skor rendah (0.3).
        $scoreInvalid = $ref->invoke($runner, 'erd', 'bukan json sama sekali');
        $this->assertSame(0.3, $scoreInvalid);
    }

    public function test_quality_score_api_contract_json_rubric(): void
    {
        $client = new AiClient;
        $runner = new PipelineRunner($this->version, $client);
        $ref = new \ReflectionMethod($runner, 'computeStageQuality');
        $ref->setAccessible(true);

        $endpoints = [];
        for ($i = 1; $i <= 12; $i++) {
            $endpoints[] = ['method' => 'GET', 'path' => "/api/r{$i}", 'description' => "ep {$i}", 'auth' => true];
        }
        $score = $ref->invoke($runner, 'api_contract', json_encode($endpoints));
        $this->assertGreaterThan(0.9, $score);

        // {endpoints:[...]} wrapper juga didukung.
        $wrapped = $ref->invoke($runner, 'api_contract', json_encode(['endpoints' => array_slice($endpoints, 0, 5)]));
        $this->assertGreaterThan(0.5, $wrapped);
        $this->assertLessThan(0.9, $wrapped);
    }
}