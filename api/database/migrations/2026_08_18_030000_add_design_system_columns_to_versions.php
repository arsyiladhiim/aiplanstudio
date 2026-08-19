<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->text('design_system')->nullable()->after('master_prompt');
            $table->text('design_system_mobile')->nullable()->after('design_system');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->dropColumn(['design_system', 'design_system_mobile']);
        });
    }
};
