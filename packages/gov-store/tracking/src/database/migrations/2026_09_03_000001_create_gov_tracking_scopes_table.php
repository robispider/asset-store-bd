<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_tracking_scopes');

        Schema::create('gov_tracking_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_code_id')->constrained('gov_tracking_codes')->onDelete('cascade');
            $table->string('dimension'); 
            $table->string('target_type'); 
            $table->unsignedBigInteger('target_id')->nullable(); 
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_scopes');
    }
};