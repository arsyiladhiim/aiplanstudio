<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->boolean('is_pinned')->default(false)->after('is_favorite');
            $table->index(['user_id', 'is_pinned']);
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_pinned']);
            $table->dropColumn('is_pinned');
        });
    }
};
