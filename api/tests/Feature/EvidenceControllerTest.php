<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\ProjectApiToken;
use App\Models\User;
use App\Models\Version;
use App\Models\VersionStageEvidence;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EvidenceControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private Version $version;

    private string $token;

    private string $secret;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->project = Project::factory()->create(['user_id' => $this->user->id]);
        $this->version = Version::factory()->create(['project_id' => $this->project->id]);

        $result = ProjectApiToken::generate($this->project, 'test-evidence');
        $this->token = $result['token'];
        $this->secret = $result['secret'];
    }

    private function postEvidence(array $body, ?string $forcedTs = null): \Illuminate\Testing\TestResponse
    {
        $bodyJson = json_encode($body, JSON_UNESCAPED_UNICODE);
        $ts = $forcedTs ?? (string) time();
        $sig = hash_hmac('sha256', $ts.'.'.$bodyJson, $this->secret);

        return $this->call(
            'POST',
            "/api/versions/{$this->version->id}/evidence",
            [], [], [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
                'HTTP_X_TOKEN_SECRET' => $this->secret,
                'HTTP_X_TIMESTAMP' => $ts,
                'HTTP_X_SIGNATURE' => $sig,
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyJson,
        );
    }

    public function test_post_evidence_baru_berhasil_dengan_signature_valid(): void
    {
        $resp = $this->postEvidence([
            'stage_key' => 'verify.review',
            'tests_passed' => true,
            'lint_passed' => true,
            'security_passed' => true,
            'perf_passed' => true,
            'files_changed' => ['src/Auth.php', 'tests/AuthTest.php'],
            'evidence_url' => 'https://ci.example.com/run/123',
            'notes' => 'Reviewed by agent',
        ]);

        $resp->assertStatus(200);
        $resp->assertJsonStructure(['ok', 'evidence_id', 'stage_key', 'version_id', 'updated_at']);

        $this->assertDatabaseHas('version_stage_evidence', [
            'version_id' => $this->version->id,
            'stage_key' => 'verify.review',
            'tests_passed' => true,
            'security_passed' => true,
        ]);
    }

    public function test_post_evidence_kedua_update_bukan_insert_baru(): void
    {
        $this->postEvidence(['stage_key' => 'verify.review', 'tests_passed' => false]);
        $this->postEvidence(['stage_key' => 'verify.review', 'tests_passed' => true, 'security_passed' => true]);

        $count = VersionStageEvidence::where('version_id', $this->version->id)
            ->where('stage_key', 'verify.review')
            ->count();
        $this->assertSame(1, $count, 'UNIQUE constraint harus membuat 1 row per stage.');

        $row = VersionStageEvidence::where('version_id', $this->version->id)
            ->where('stage_key', 'verify.review')
            ->first();
        $this->assertTrue($row->tests_passed);
        $this->assertTrue($row->security_passed);
    }

    public function test_post_evidence_invalid_stage_key_returns_422(): void
    {
        $resp = $this->postEvidence(['stage_key' => 'bogus.stage']);
        $resp->assertStatus(422);
        $resp->assertJsonStructure(['message']);
    }

    public function test_post_evidence_invalid_signature_returns_401(): void
    {
        $bodyJson = json_encode(['stage_key' => 'verify.review']);
        $ts = (string) time();
        $wrongSig = hash_hmac('sha256', $ts.'.'.$bodyJson, 'wrong-secret');

        $resp = $this->call(
            'POST',
            "/api/versions/{$this->version->id}/evidence",
            [], [], [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.$this->token,
                'HTTP_X_TOKEN_SECRET' => $this->secret,
                'HTTP_X_TIMESTAMP' => $ts,
                'HTTP_X_SIGNATURE' => $wrongSig,
                'CONTENT_TYPE' => 'application/json',
            ],
            $bodyJson,
        );
        $resp->assertStatus(401);
    }

    public function test_post_evidence_replay_returns_409(): void
    {
        $body = ['stage_key' => 'verify.review', 'tests_passed' => true];
        $first = $this->postEvidence($body);
        $first->assertStatus(200);

        // Replay persis signature + timestamp.
        $second = $this->postEvidence($body);
        $second->assertStatus(409);
    }

    public function test_get_evidence_via_sanctum(): void
    {
        $this->postEvidence(['stage_key' => 'verify.review', 'tests_passed' => true, 'security_passed' => true]);

        $resp = $this->actingAs($this->user, 'sanctum')
            ->getJson("/api/versions/{$this->version->id}/evidence");
        $resp->assertStatus(200);
        $resp->assertJsonPath('data.0.stage_key', 'verify.review');
        $resp->assertJsonPath('data.0.tests_passed', true);
        $resp->assertJsonPath('data.0.security_passed', true);
    }

    public function test_get_evidence_other_user_returns_404(): void
    {
        $other = User::factory()->create();
        $resp = $this->actingAs($other, 'sanctum')
            ->getJson("/api/versions/{$this->version->id}/evidence");
        $resp->assertStatus(404);
    }

    public function test_evidence_url_optional(): void
    {
        $resp = $this->postEvidence([
            'stage_key' => 'smoke_test',
            'tests_passed' => true,
            'build_passed' => true,
        ]);
        $resp->assertStatus(200);
        $this->assertDatabaseHas('version_stage_evidence', [
            'stage_key' => 'smoke_test',
            'evidence_url' => null,
        ]);
    }
}
