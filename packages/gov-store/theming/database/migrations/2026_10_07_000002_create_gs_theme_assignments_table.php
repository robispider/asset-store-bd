<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gs_theme_assignments')) {
            return;
        }
        Schema::create('gs_theme_assignments', function (Blueprint $table) {
            $table->id();
            $table->enum('scope_type', ['organization', 'company', 'office']);
            // null only for organization; companies.id / locations.id otherwise.
            $table->unsignedInteger('scope_id')->nullable();
            $table->string('theme', 64);
            $table->boolean('enforced')->default(false);
            $table->unsignedInteger('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['scope_type', 'scope_id']);
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gs_theme_assignments');
    }
};
