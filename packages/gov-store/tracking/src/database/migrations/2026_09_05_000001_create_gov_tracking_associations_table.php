<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_tracking_associations');

        Schema::create('gov_tracking_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_code_id')->constrained('gov_tracking_codes')->onDelete('cascade');
            
            // Core contextual values saved directly to maintain 100% database autonomy
            $table->unsignedInteger('category_id'); 
            $table->unsignedInteger('location_id'); // Directly stored receiving office id
            $table->integer('quantity'); 
            
            // Polymorphic link to the physical entity (Asset or Consumable movement)
            $table->string('associatable_type'); 
            $table->unsignedBigInteger('associatable_id');
            
            $table->string('status')->default('ACTIVE'); // ACTIVE, DEPRECATED
            $table->timestamps();

            $table->index(['associatable_type', 'associatable_id'], 'idx_assoc_polymorphic');
            $table->index(['tracking_code_id', 'category_id', 'location_id', 'status'], 'idx_tracking_progress_sums');
            $table->unique(['tracking_code_id', 'associatable_type', 'associatable_id'], 'uq_code_assoc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_associations');
    }
};