<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gov_model_metadata_states', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('model_id'); // maps to core models.id
            $table->string('provider_name');
            $table->string('version');
            $table->timestamps();

            // Enforce unique entries per model + provider coupling
            $table->unique(['model_id', 'provider_name']);
            
            $table->foreign('model_id')
                  ->references('id')
                  ->on('models')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_model_metadata_states');
    }
};