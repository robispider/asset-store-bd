<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_initiatives');

        Schema::create('gov_initiatives', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('purpose')->nullable();
            $table->string('status')->default('Planning'); // Planning, Active, Closed, Archived
            $table->string('primary_funding'); // ADP, REVENUE, OTHER
            
            $table->boolean('require_documents')->default(true);
            $table->boolean('allow_overshoot')->default(false);
            $table->boolean('require_metadata')->default(false);
            
            $table->unsignedInteger('owner_company_id')->nullable();
            $table->unsignedInteger('manager_location_id')->nullable();
            
            $table->timestamps();

            $table->foreign('owner_company_id')->references('id')->on('companies')->onDelete('restrict');
            $table->foreign('manager_location_id')->references('id')->on('locations')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_initiatives');
    }
};