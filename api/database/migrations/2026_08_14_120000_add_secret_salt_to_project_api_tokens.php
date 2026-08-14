<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_project.project_api_tokens', function (Blueprint $table) {
            $table->string('secret_salt', 32)->nullable()->after('secret_hash');
        });

        // Backfill via PHP agar portable (no Postgres-specific functions).
        // Existing token dengan secret_hash ada di-null-kan agar dipaksa regenerate.
        DB::table('aiplanstudio_project.project_api_tokens')
            ->whereNotNull('secret_hash')
            ->whereNull('secret_salt')
            ->orderBy('id')
            ->chunkById(100, function ($rows) {
                foreach ($rows as $row) {
                    DB::table('aiplanstudio_project.project_api_tokens')
                        ->where('id', $row->id)
                        ->update([
                            'secret_salt' => bin2hex(random_bytes(16)),
                            'secret_hash' => null,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.project_api_tokens', function (Blueprint $table) {
            $table->dropColumn('secret_salt');
        });
    }
};
