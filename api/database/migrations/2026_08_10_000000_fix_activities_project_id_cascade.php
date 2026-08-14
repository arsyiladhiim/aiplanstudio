<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.activities', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
        DB::statement('ALTER TABLE aiplanstudio_project.activities ALTER COLUMN project_id DROP NOT NULL');
        DB::statement('ALTER TABLE aiplanstudio_project.activities ADD CONSTRAINT activities_project_id_foreign FOREIGN KEY (project_id) REFERENCES aiplanstudio_project.projects(id) ON DELETE SET NULL');
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.activities', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
        });
        DB::statement('ALTER TABLE aiplanstudio_project.activities ALTER COLUMN project_id SET NOT NULL');
        DB::statement('ALTER TABLE aiplanstudio_project.activities ADD CONSTRAINT activities_project_id_foreign FOREIGN KEY (project_id) REFERENCES aiplanstudio_project.projects(id) ON DELETE CASCADE');
    }
};
