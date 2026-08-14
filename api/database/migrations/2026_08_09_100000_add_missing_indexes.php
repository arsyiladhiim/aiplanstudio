<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.project_api_tokens', function (Blueprint $table) {
            $table->index('project_id');
        });

        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->index('source_version_id');
        });

        DB::statement(
            'CREATE UNIQUE INDEX ai_providers_is_active_unique '
            . 'ON aiplanstudio_settings.ai_providers (is_active) WHERE is_active = true'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ai_providers_is_active_unique');

        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->dropIndex(['source_version_id']);
        });

        Schema::table('aiplanstudio_project.project_api_tokens', function (Blueprint $table) {
            $table->dropIndex(['project_id']);
        });
    }
};
