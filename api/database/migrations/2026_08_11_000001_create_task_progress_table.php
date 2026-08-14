<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aiplanstudio_project.task_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('phase_progress_id')->constrained('aiplanstudio_project.phase_progress')->cascadeOnDelete();
            $table->string('task_key');
            $table->string('task_type');
            $table->string('title');
            $table->string('status')->default('pending');
            $table->string('checkpoint')->nullable();
            $table->text('output')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['phase_progress_id', 'task_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aiplanstudio_project.task_progress');
    }
};
