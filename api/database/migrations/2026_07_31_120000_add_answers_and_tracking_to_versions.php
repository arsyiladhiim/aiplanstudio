<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->jsonb('answers')->nullable()->after('stage_status');
            $table->text('tracking_token')->nullable()->after('master_prompt');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->dropColumn('answers');
            $table->dropColumn('tracking_token');
        });
    }
};
