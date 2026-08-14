<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE aiplanstudio_master.users "
            . "ADD CONSTRAINT users_status_check "
            . "CHECK (status IN ('active', 'pending'))"
        );

        DB::statement(
            "ALTER TABLE aiplanstudio_settings.ai_providers "
            . "ADD CONSTRAINT ai_providers_provider_type_check "
            . "CHECK (provider_type IN ('openai', 'anthropic', 'mistral', 'cohere', 'custom'))"
        );

        DB::statement(
            "ALTER TABLE aiplanstudio_project.phase_progress "
            . "ADD CONSTRAINT phase_progress_status_check "
            . "CHECK (status IN ('pending', 'running', 'done', 'error'))"
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE aiplanstudio_project.phase_progress DROP CONSTRAINT IF EXISTS phase_progress_status_check');
        DB::statement('ALTER TABLE aiplanstudio_settings.ai_providers DROP CONSTRAINT IF EXISTS ai_providers_provider_type_check');
        DB::statement('ALTER TABLE aiplanstudio_master.users DROP CONSTRAINT IF EXISTS users_status_check');
    }
};
