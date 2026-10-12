<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gov_office_starter_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('location_id')->unique();
            $table->unsignedInteger('company_id');
            $table->unsignedInteger('actor_id');
            $table->string('office_type', 30);
            $table->string('status', 30)->default('pending');
            $table->json('snapshot')->nullable();
            $table->uuid('failure_reference')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_office_starter_runs');
    }
};
