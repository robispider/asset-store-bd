<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gov_metadata_field_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('identifier')->unique(); // e.g. 'govstore.baseline.grn'
            $table->unsignedInteger('custom_field_id'); // maps to core custom_fields.id
            $table->timestamps();

            $table->foreign('custom_field_id')
                  ->references('id')
                  ->on('custom_fields')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_metadata_field_mappings');
    }
};
