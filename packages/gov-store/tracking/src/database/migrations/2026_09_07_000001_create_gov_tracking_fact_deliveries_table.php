<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_tracking_fact_deliveries');

        Schema::create('gov_tracking_fact_deliveries', function (Blueprint $table) {
            $table->id();
            
            // --- DIMENSIONS (Where / When / What / Who) ---
            $table->foreignId('initiative_id')->constrained('gov_initiatives')->onDelete('cascade');
            $table->foreignId('tracking_code_id')->constrained('gov_tracking_codes')->onDelete('cascade');
            $table->foreignId('funding_type_id')->constrained('gov_funding_types')->onDelete('restrict');
            $table->string('fiscal_year', 20);
            
            $table->unsignedInteger('location_id'); // references core locations.id
            $table->unsignedInteger('geo_area_id')->nullable(); // references gov_geo_areas.GeoAreaId
            $table->unsignedInteger('category_id'); // references core categories.id
            
            // FIXED: Mapped dimensions are made nullable to prevent crashes on incomplete assets
            $table->unsignedInteger('model_id')->nullable(); 
            $table->unsignedInteger('manufacturer_id')->nullable(); 
            $table->unsignedInteger('supplier_id')->nullable(); 

            // --- FACTS (Additive Metrics) ---
            $table->integer('received_qty')->default(0);
            $table->decimal('total_cost', 15, 2)->default(0.00);
            $table->integer('transaction_count')->default(0);

            $table->timestamps();

            // --- FOREIGN KEY RESTRAINTS TO NATIVE SNIPE-IT CATALOGS ---
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('geo_area_id')->references('GeoAreaId')->on('gov_geo_areas')->onDelete('set null');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->foreign('model_id')->references('id')->on('models')->onDelete('set null');
            $table->foreign('manufacturer_id')->references('id')->on('manufacturers')->onDelete('set null');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');

            // --- COMPOSITE INDEXES ---
            // On MySQL, unique indexes fully support nullable columns (treated as distinct)
            $table->unique(
                ['tracking_code_id', 'location_id', 'category_id', 'model_id', 'manufacturer_id', 'supplier_id'], 
                'uq_delivery_dimensions'
            );
            
            $table->index(['initiative_id', 'category_id', 'location_id'], 'idx_reporting_search');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_fact_deliveries');
    }
};