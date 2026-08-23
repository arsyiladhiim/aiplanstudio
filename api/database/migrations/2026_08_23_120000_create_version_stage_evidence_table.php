<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('version_stage_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('version_id')->constrained('versions')->cascadeOnDelete();
            $table->string('stage_key', 64);
            $table->string('task_key', 128)->nullable();
            $table->jsonb('files_changed')->nullable();
            $table->boolean('tests_passed')->default(false);
            $table->boolean('lint_passed')->default(false);
            $table->boolean('build_passed')->default(false);
            $table->boolean('migrate_passed')->default(false);
            $table->boolean('security_passed')->default(false);
            $table->boolean('perf_passed')->default(false);
            $table->text('evidence_url')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            // CP-46.B: 1 evidence row per stage (per Q-A answer).
            $table->unique(['version_id', 'stage_key'], 'version_stage_evidence_version_stage_unique');
            $table->index(['project_id', 'stage_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('version_stage_evidence');
    }
};
