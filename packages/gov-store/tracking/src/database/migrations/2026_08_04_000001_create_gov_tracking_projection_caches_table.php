<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_tracking_projection_caches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_reference_id')->unique()->constrained('gov_tracking_references')->onDelete('cascade');
            $table->integer('planned')->default(0);
            $table->integer('ordered')->default(0);
            $table->integer('received')->default(0);
            $table->integer('deployed')->default(0);
            $table->integer('disposed')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_projection_caches');
    }
};