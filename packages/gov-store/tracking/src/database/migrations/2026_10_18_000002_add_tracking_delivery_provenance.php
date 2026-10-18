<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_tracking_deliveries', function (Blueprint $table) {
            $table->string('movement_id', 64)->nullable()->index();
            $table->string('document_item_id', 64)->nullable()->index();
            // Immutable receipt-time dimensions; legacy rows intentionally remain unknown.
            $table->json('receipt_snapshot')->nullable();
        });
        Schema::table('gov_tracking_associations', function (Blueprint $table) {
            $table->foreignId('delivery_id')->nullable()->constrained('gov_tracking_deliveries')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Receipt provenance is retained; dropping it would destroy financial evidence.
    }
};
