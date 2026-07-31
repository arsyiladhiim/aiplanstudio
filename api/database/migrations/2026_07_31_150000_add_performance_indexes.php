<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // PostgreSQL does not auto-index foreign keys — explicit indexes needed
        Schema::table('aiplanstudio_project.activities', function (Blueprint $table) {
            $table->index('project_id');
            $table->index('version_id');
            $table->index('user_id');
            $table->index('action');
        });

        Schema::table('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->index('user_id');
        });

        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('aiplanstudio_project.phase_progress', function (Blueprint $table) {
            $table->index('version_id');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.activities', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
            $table->dropIndex(['version_id']);
            $table->dropIndex(['user_id']);
            $table->dropIndex(['action']);
        });

        Schema::table('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });

        Schema::table('aiplanstudio_project.phase_progress', function (Blueprint $table) {
            $table->dropIndex(['version_id']);
        });
    }
};
