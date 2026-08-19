<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use App\Services\AiClient;
use App\Services\PipelineRunner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KeywordSynonymTest extends TestCase
{
    use RefreshDatabase;

    private function assertFor(string $stage, string $content, bool $shouldPass): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $version = Version::factory()->create(['project_id' => $project->id]);
        $client = new AiClient;
        $runner = new PipelineRunner($version, $client);
        $ref = new \ReflectionMethod($runner, 'assertRequiredKeywords');
        $ref->setAccessible(true);

        if ($shouldPass) {
            $ref->invoke($runner, $stage, $content);
            $this->assertTrue(true);
        } else {
            $this->expectException(\RuntimeException::class);
            $ref->invoke($runner, $stage, $content);
        }
    }

    public function test_architecture_passes_with_indonesian_vocab(): void
    {
        $this->assertFor('architecture', "# Arsitektur\n\n## 1. Stack\nBackend: Laravel, Frontend: Next.js\n## 2. Module Boundaries\nPemisahan module jelas.\n## 6. Trade-offs\nTabel keputusan.", true);
    }

    public function test_architecture_fails_when_no_group_matches(): void
    {
        $this->assertFor('architecture', "# Arsitektur\n\n## 1. Pendahuluan\nLorem ipsum.\n## 2. Catatan\nTanpa stack atau trade-off.", false);
    }

    public function test_security_passes_with_indonesian_vocab(): void
    {
        $this->assertFor('security', "# Security\n\n## 1. Autentikasi & Session\nSanctum.\n## 2. Otorisasi\nRBAC.\n## 9. Checklist\n- [ ] Enkripsi", true);
    }

    public function test_security_fails_when_keywords_absent(): void
    {
        $this->assertFor('security', "# Security\n\n## 1. Pendahuluan\nTeks tanpa autentikasi atau otorisasi.", false);
    }

    public function test_design_system_passes_signature_synonyms(): void
    {
        $this->assertFor('design_system', "# DS\n\n## 3. Signature Element\nElemen khas unik.\n## 6. Anti-Pattern Checklist\n- [ ] No gradient\n## 2. Token System\n--color-bg", true);
    }

    public function test_save_artifact_architecture_realistic_passes(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['user_id' => $user->id]);
        $version = Version::factory()->create(['project_id' => $project->id]);
        $client = new AiClient;
        $runner = new PipelineRunner($version, $client);
        $ref = new \ReflectionMethod($runner, 'saveArtifact');
        $ref->setAccessible(true);

        $content = "# Architecture: Demo\n\n## 1. Stack (with reasoning)\nBackend: Laravel, Frontend: Next.js\n## 2. Module Boundaries\nPemisahan jelas.\n```\n┌─────────────┐\n│   Browser   │\n└──────┬──────┘\n       │\n┌──────▼──────┐\n│   Laravel   │\n└─────────────┘\n```\n## 3. Data Flow\nRequest lifecycle.\n## 4. Folder Structure\nsrc/\n## 5. Deployment Topology\nDocker Compose.\n## 6. Trade-offs\n| Decision | Alternative | Why we chose this |\n|----------|-------------|-------------------|\n| Sanctum | JWT | Aman |\n| Direct | BFF | Cepat |\n| Single VPS | K8s | Hemat |";
        $ref->invoke($runner, 'architecture', $content);
        $this->assertTrue(true);
    }
}