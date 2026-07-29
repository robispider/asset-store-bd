<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_tracking_associations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tracking_reference_id')->constrained('gov_tracking_references')->onDelete('cascade');
            $table->string('associatable_type'); // Polymorphic (e.g., App\Models\Asset, App\Models\Actionlog)
            $table->unsignedBigInteger('associatable_id');
            $table->string('status')->default('ACTIVE'); // ACTIVE, DEPRECATED
            $table->timestamps();

            $table->index(['associatable_type', 'associatable_id'], 'idx_assoc_polymorphic');
            $table->unique(['tracking_reference_id', 'associatable_type', 'associatable_id'], 'uq_ref_assoc');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_associations');
    }
};
