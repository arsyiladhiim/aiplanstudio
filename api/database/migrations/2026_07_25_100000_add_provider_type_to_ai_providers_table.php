<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_settings.ai_providers', function (Blueprint $table) {
            $table->string('name', 100)->default('AI Provider')->after('id');
            $table->string('provider_type', 20)->default('openai')->after('base_url');
            $table->boolean('is_active')->default(false)->after('model');
            $table->text('last_test_response')->nullable()->after('is_active');
            $table->timestamp('last_test_at')->nullable()->after('last_test_response');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_settings.ai_providers', function (Blueprint $table) {
            $table->dropColumn(['name', 'provider_type', 'is_active', 'last_test_response', 'last_test_at']);
        });
    }
};
