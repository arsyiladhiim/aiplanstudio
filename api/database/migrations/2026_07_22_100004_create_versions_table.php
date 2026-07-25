<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->integer('version_no');
            $table->jsonb('stage_status')->nullable(); // {analisa: 'done', prd: 'running', ...}
            $table->text('analysis')->nullable();
            $table->text('prd')->nullable();
            $table->text('architecture')->nullable();
            $table->jsonb('erd')->nullable(); // {nodes:[], edges:[]}
            $table->jsonb('api_contract')->nullable(); // [{method, path, desc}]
            $table->jsonb('phases')->nullable(); // [{key,title,prompt}]
            $table->text('master_prompt')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'version_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versions');
    }
};
