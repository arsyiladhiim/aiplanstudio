<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->timestampTz('archived_at')->nullable()->after('is_pinned');
            $table->index(['user_id', 'archived_at']);
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'archived_at']);
            $table->dropColumn('archived_at');
        });
    }
};
