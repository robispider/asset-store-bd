<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gs_theme_preferences')) {
            return;
        }
        Schema::create('gs_theme_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('user_id')->unique();
            // null = follow the office / company / organisation default.
            $table->string('theme', 64)->nullable();
            $table->enum('mode', ['light', 'dark', 'system'])->default('system');
            $table->timestamps();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gs_theme_preferences');
    }
};
