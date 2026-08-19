<?php

namespace Tests\Unit\PromptValidation;

use App\Models\Project;
use App\Models\User;
use App\Models\Version;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkipReasonTest extends TestCase
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

    public function test_skip_stage_records_reason_and_marks_done(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$this->version->id}/skip-stage", [
                'stage' => 'pertanyaan_mobile',
                'reason' => 'Aplikasi web-only, mobile tidak relevan',
            ]);

        $resp->assertOk()
            ->assertJson(['ok' => true, 'skipped' => true]);

        $this->version->refresh();
        $this->assertSame('done', ($this->version->stage_status)['pertanyaan_mobile']);
        $this->assertSame('Aplikasi web-only, mobile tidak relevan', ($this->version->skip_reasons)['pertanyaan_mobile']);
    }

    public function test_cannot_skip_stage_already_done(): void
    {
        $this->version->stage_status = ['prd' => 'done'];
        $this->version->save();

        $resp = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$this->version->id}/skip-stage", [
                'stage' => 'prd',
                'reason' => 'sudah selesai',
            ]);

        $resp->assertStatus(422);
        $this->version->refresh();
        $this->assertArrayNotHasKey('prd', $this->version->skip_reasons ?? []);
    }

    public function test_reason_required(): void
    {
        $resp = $this->actingAs($this->user, 'sanctum')
            ->postJson("/api/versions/{$this->version->id}/skip-stage", [
                'stage' => 'pertanyaan_mobile',
            ]);

        $resp->assertStatus(422);
        $this->version->refresh();
        $this->assertEmpty($this->version->skip_reasons ?? []);
    }

    public function test_multiple_skips_accumulate(): void
    {
        foreach (['env_config', 'security'] as $stage) {
            $this->actingAs($this->user, 'sanctum')
                ->postJson("/api/versions/{$this->version->id}/skip-stage", [
                    'stage' => $stage,
                    'reason' => "skip {$stage}",
                ])->assertOk();
        }

        $this->version->refresh();
        $this->assertCount(2, $this->version->skip_reasons);
        $this->assertSame('skip env_config', ($this->version->skip_reasons)['env_config']);
        $this->assertSame('skip security', ($this->version->skip_reasons)['security']);
    }
}