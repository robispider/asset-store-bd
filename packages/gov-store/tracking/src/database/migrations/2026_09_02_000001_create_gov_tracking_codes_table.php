<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_tracking_codes');

        Schema::create('gov_tracking_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained('gov_initiatives')->onDelete('cascade');
            $table->foreignId('funding_type_id')->nullable()->constrained('gov_funding_types')->onDelete('restrict');
            
            $table->string('tracking_code')->unique();
            $table->string('task_title');
            
            // Progressive Specificity Model: 1_BLANKET, 2_CATEGORY, 3_MATRIX
            $table->string('specificity_level', 20)->default('2_CATEGORY'); 
            
            $table->string('fiscal_year', 20)->nullable();
            $table->string('status')->default('DRAFT');
            $table->string('order_pdf_path')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_codes');
    }
};