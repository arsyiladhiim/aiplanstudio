<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.phase_progress', function (Blueprint $table) {
            $table->text('output')->nullable()->after('done');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.phase_progress', function (Blueprint $table) {
            $table->dropColumn('output');
        });
    }
};
