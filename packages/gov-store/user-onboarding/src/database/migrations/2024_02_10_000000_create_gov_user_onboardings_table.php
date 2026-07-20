<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gov_user_onboardings', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->unique();
            $table->string('status', 30)->default('WAITING'); // WAITING, COMPLETED, CANCELLED
            
            $table->unsignedInteger('creator_user_id');
            $table->string('owner_type', 50); // OFFICE_ADMIN, ICT_OFFICER, COMPANY_ADMIN, SYSTEM
            
            $table->unsignedInteger('owner_id'); // User ID of the managing authority
            
            $table->unsignedInteger('geo_area_id')->nullable(); // Spatially tags orphans
            $table->unsignedInteger('assigned_membership_id')->nullable();
            
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('creator_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('geo_area_id')->references('GeoAreaId')->on('gov_geo_areas')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_user_onboardings');
    }
};
