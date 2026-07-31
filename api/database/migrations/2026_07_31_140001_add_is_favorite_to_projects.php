<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->boolean('is_favorite')->default(false)->after('stack');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->dropColumn('is_favorite');
        });
    }
};
