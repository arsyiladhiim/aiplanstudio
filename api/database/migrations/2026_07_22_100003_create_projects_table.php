<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aiplanstudio_project.projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('aiplanstudio_master.users')->cascadeOnDelete();
            $table->string('title');
            $table->text('idea');
            $table->enum('target', ['web', 'both'])->default('web');
            $table->string('stack')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aiplanstudio_project.projects');
    }
};
