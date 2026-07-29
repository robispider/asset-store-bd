<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_tracking_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_reference_id')->constrained('gov_tracking_references')->onDelete('cascade');
            $table->unsignedInteger('category_id'); // references core categories.id
            $table->unsignedInteger('model_id')->nullable(); // references core models.id
            $table->integer('planned_qty');
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->foreign('model_id')->references('id')->on('models')->onDelete('restrict');
            
            // Prevent duplicate targets for the same reference/category/model combination
            $table->unique(['tracking_reference_id', 'category_id', 'model_id'], 'uq_ref_cat_model');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_targets');
    }
};
