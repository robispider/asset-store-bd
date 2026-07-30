<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_tracking_allocations');

        Schema::create('gov_tracking_allocations', function (Blueprint $table) {
            $table->id();
            
            // The Master Goal (e.g., Laptops)
            $table->foreignId('target_id')->constrained('gov_tracking_targets')->onDelete('cascade');
            
            // The Specific Cell Destination
            $table->unsignedInteger('location_id'); // references core locations.id
            
            // The Exact Allocation for this office
            $table->integer('allocated_qty');
            
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('restrict');
            
            // A location can only have one allocation cell per master target line
            $table->unique(['target_id', 'location_id'], 'uq_target_location_alloc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_allocations');
    }
};