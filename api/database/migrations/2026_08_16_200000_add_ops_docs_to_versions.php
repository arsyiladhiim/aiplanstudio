<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->text('env_config')->nullable()->after('mobile_agents');
            $table->text('security')->nullable()->after('env_config');
            $table->text('deployment')->nullable()->after('security');
            $table->text('observability')->nullable()->after('deployment');
        });
    }

    public function down(): void
    {
        Schema::table('versions', function (Blueprint $table) {
            $table->dropColumn(['observability', 'deployment', 'security', 'env_config']);
        });
    }
};