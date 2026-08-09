<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.phase_progress', function (Blueprint $table) {
            $table->string('status')->default('pending')->after('done');
            $table->timestamp('started_at')->nullable()->after('status');
            $table->timestamp('finished_at')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.phase_progress', function (Blueprint $table) {
            $table->dropColumn(['status', 'started_at', 'finished_at']);
        });
    }
};