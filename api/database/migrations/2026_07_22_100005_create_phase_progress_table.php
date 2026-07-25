<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phase_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('version_id')->constrained()->cascadeOnDelete();
            $table->string('phase_key'); // merujuk phases[].key
            $table->boolean('done')->default(false);
            $table->timestamps();

            $table->unique(['version_id', 'phase_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phase_progress');
    }
};
