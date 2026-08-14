<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.project_api_tokens', function (Blueprint $table) {
            $table->string('secret_hash', 64)->nullable()->after('token_hash');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.project_api_tokens', function (Blueprint $table) {
            $table->dropColumn('secret_hash');
        });
    }
};
