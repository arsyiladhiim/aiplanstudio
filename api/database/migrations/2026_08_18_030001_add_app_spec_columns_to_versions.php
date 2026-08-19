<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->jsonb('app_spec_web')->nullable()->after('design_system_mobile');
            $table->jsonb('app_spec_mobile')->nullable()->after('app_spec_web');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->dropColumn(['app_spec_web', 'app_spec_mobile']);
        });
    }
};
