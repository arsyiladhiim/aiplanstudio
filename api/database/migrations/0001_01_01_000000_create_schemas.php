<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['aiplanstudio_master', 'aiplanstudio_project', 'aiplanstudio_settings'] as $schema) {
            DB::statement("CREATE SCHEMA IF NOT EXISTS {$schema}");
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        foreach (['aiplanstudio_master', 'aiplanstudio_project', 'aiplanstudio_settings'] as $schema) {
            DB::statement("DROP SCHEMA IF EXISTS {$schema} CASCADE");
        }
    }
};
