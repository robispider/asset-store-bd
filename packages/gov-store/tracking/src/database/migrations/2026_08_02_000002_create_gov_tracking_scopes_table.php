<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_tracking_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_reference_id')->constrained('gov_tracking_references')->onDelete('cascade');
            $table->string('dimension'); // OWNERSHIP, VISIBILITY, APPLICABILITY, ADMINISTRATION
            $table->string('target_type'); // Company, Location, GeoArea, Global
            $table->unsignedBigInteger('target_id')->nullable(); // Matches PK of targeted entity
            $table->timestamps();

            $table->index(['tracking_reference_id', 'dimension']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_scopes');
    }
};
