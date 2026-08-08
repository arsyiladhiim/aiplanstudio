<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aiplanstudio_master.templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('target', ['web', 'both']);
            $table->text('description')->nullable();
            $table->jsonb('seed')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aiplanstudio_master.templates');
    }
};
