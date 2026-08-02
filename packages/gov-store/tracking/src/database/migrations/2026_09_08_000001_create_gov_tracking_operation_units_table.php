<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_tracking_operation_units');

        Schema::create('gov_tracking_operation_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained('gov_initiatives')->onDelete('cascade');
            
            // Link to native Snipe-IT users
            $table->unsignedInteger('user_id'); 
            
            // Mapped designations (Now including MONITOR)
            $table->enum('designation', ['HEAD', 'OFFICER', 'SUPPORT', 'MONITOR']); 
            
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            $table->unique(['initiative_id', 'user_id', 'designation'], 'uq_initiative_user_designation');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_operation_units');
    }
};