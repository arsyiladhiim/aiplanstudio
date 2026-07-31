<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aiplanstudio_project.activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('aiplanstudio_project.projects')->cascadeOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('aiplanstudio_project.versions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('aiplanstudio_master.users')->cascadeOnDelete();
            $table->string('action'); // created_version, deleted_version, updated_artifact, toggled_phase, etc
            $table->text('description');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aiplanstudio_project.activities');
    }
};
