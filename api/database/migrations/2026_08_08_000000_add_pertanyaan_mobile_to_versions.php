<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->text('pertanyaan_mobile')->nullable()->after('pertanyaan');
            $table->jsonb('mobile_answers')->nullable()->after('answers');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->dropColumn(['pertanyaan_mobile', 'mobile_answers']);
        });
    }
};
