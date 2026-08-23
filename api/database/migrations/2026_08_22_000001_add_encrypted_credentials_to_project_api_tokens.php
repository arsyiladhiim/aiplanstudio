<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // CP-44 CP-02: simpan salinan terenkripsi token+secret agar master prompt
        // dapat menyematkan kredensial tracking untuk coding agent eksternal.
        Schema::table('aiplanstudio_project.project_api_tokens', function ($table) {
            $table->text('token_encrypted')->nullable();
            $table->text('secret_encrypted')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_project.project_api_tokens', function ($table) {
            $table->dropColumn(['token_encrypted', 'secret_encrypted']);
        });
    }
};
