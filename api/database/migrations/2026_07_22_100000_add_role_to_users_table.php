<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aiplanstudio_master.users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'member'])->default('member')->after('password');
        });
    }

    public function down(): void
    {
        Schema::table('aiplanstudio_master.users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};
