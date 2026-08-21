<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aiplanstudio_settings.research_agent_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(false);
            $table->string('search_provider')->default('tavily');
            $table->text('search_api_key')->nullable();
            $table->unsignedBigInteger('ai_provider_id')->nullable();
            $table->unsignedSmallInteger('max_per_day')->default(5);
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_run_status')->nullable();
            $table->timestamps();
        });

        Schema::create('aiplanstudio_settings.research_ideas', function (Blueprint $table) {
            $table->id();
            $table->date('window_date');
            $table->string('title');
            $table->text('target_users');
            $table->text('problem');
            $table->text('solution');
            $table->jsonb('sources')->nullable();
            $table->timestamps();
            $table->index(['window_date']);
        });

        \DB::statement('CREATE UNIQUE INDEX research_ideas_window_title_unique ON aiplanstudio_settings.research_ideas (window_date, lower(title))');
    }

    public function down(): void
    {
        Schema::dropIfExists('aiplanstudio_settings.research_ideas');
        Schema::dropIfExists('aiplanstudio_settings.research_agent_settings');
    }
};
