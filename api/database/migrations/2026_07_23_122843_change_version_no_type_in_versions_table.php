<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table("aiplanstudio_project.versions", function (Blueprint $table) {
            $table->integer("version_no")->change();
        });
    }

    public function down(): void
    {
        Schema::table("aiplanstudio_project.versions", function (Blueprint $table) {
            $table->unsignedTinyInteger("version_no")->change();
        });
    }
};
