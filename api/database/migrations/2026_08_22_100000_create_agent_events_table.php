<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CP-44 CP-07: Agent Event Protocol v1 — telemetry granular dari coding agent.
        Schema::create('aiplanstudio_project.agent_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('aiplanstudio_project.projects')->cascadeOnDelete();
            $table->unsignedBigInteger('version_id')->index();
            $table->string('run_id', 64)->index();
            $table->string('event_id', 128)->unique();
            $table->string('event', 64)->index();
            $table->string('phase_key', 128)->nullable();
            $table->string('task_key', 255)->nullable();
            $table->string('status', 32)->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aiplanstudio_project.agent_events');
    }
};
