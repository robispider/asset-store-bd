<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_tracking_references', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_type_id')->constrained('gov_tracking_types')->onDelete('restrict');
            $table->string('reference_code')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, APPROVED, ACTIVE, SUSPENDED, COMPLETED, CANCELLED
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_references');
    }
};
