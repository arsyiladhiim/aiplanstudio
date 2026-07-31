<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->jsonb('mobile_phases')->nullable()->after('phases');
            $table->text('mobile_master_prompt')->nullable()->after('master_prompt');
            $table->text('mobile_standards')->nullable()->after('standards');
            $table->text('mobile_agents')->nullable()->after('agents');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->dropColumn(['mobile_phases', 'mobile_master_prompt', 'mobile_standards', 'mobile_agents']);
        });
    }
};
