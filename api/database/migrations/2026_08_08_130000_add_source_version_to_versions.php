<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->unsignedBigInteger('source_version_id')->nullable()->after('version_no');
            $table->string('baseline_notes', 500)->nullable()->after('source_version_id');

            $table->foreign('source_version_id')
                ->references('id')
                ->on('aiplanstudio_project.versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.versions', function (Blueprint $table) {
            $table->dropForeign(['source_version_id']);
            $table->dropColumn(['source_version_id', 'baseline_notes']);
        });
    }
};