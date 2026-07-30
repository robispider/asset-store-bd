<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_tracking_targets');

        Schema::create('gov_tracking_targets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_code_id')->constrained('gov_tracking_codes')->onDelete('cascade');
            $table->unsignedInteger('category_id');
            $table->integer('planned_qty');
            $table->string('economic_code', 50)->nullable();
            
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('restrict');
            $table->unique(['tracking_code_id', 'category_id'], 'uq_trk_code_cat');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_targets');
    }
};