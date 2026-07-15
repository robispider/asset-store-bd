<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGovOrganizationTables extends Migration
{
    public function up()
    {
        // 1. Drop existing tables to ensure clean compilation
        Schema::dropIfExists('gov_organization_activity_logs');
        Schema::dropIfExists('gov_location_roles');
        Schema::dropIfExists('gov_ict_jurisdictions');
        Schema::dropIfExists('gov_location_profiles');

        // 2. Create Location Profiles (Fixed: geo_area_id changed to unsignedInteger to match GeoAreaId size)
        Schema::create('gov_location_profiles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id')->unsigned()->unique();
            $table->unsignedInteger('geo_area_id'); // 32-bit unsigned integer matching GeoAreaId
            $table->integer('office_admin_id')->unsigned()->nullable();
            $table->string('lifecycle_status', 30)->default('provisioned')->index();
            $table->timestamp('geo_area_verified_at')->nullable();
            $table->integer('geo_area_verified_by')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('geo_area_id')->references('GeoAreaId')->on('gov_geo_areas')->onDelete('restrict');
            $table->foreign('office_admin_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('geo_area_verified_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Create ICT Officer Jurisdictions (Fixed: geo_area_id changed to unsignedInteger to match GeoAreaId size)
        Schema::create('gov_ict_jurisdictions', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->unique();
            $table->unsignedInteger('geo_area_id'); // 32-bit unsigned integer matching GeoAreaId
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('geo_area_id')->references('GeoAreaId')->on('gov_geo_areas')->onDelete('restrict');
        });

        // 4. Create Location Roles
        Schema::create('gov_location_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id')->unsigned()->unique();
            
            $table->integer('primary_approver_id')->unsigned()->nullable();
            $table->integer('primary_delegate_id')->unsigned()->nullable();
            $table->date('primary_delegate_until')->nullable();
            
            $table->integer('final_approver_id')->unsigned()->nullable();
            $table->integer('final_delegate_id')->unsigned()->nullable();
            $table->date('final_delegate_until')->nullable();
            
            $table->integer('storekeeper_id')->unsigned()->nullable();
            $table->integer('storekeeper_delegate_id')->unsigned()->nullable();
            $table->date('storekeeper_delegate_until')->nullable();
            
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('primary_approver_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('primary_delegate_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('final_approver_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('final_delegate_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('storekeeper_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('storekeeper_delegate_id')->references('id')->on('users')->onDelete('set null');
        });

        // 5. Create Organization Activity Log
        Schema::create('gov_organization_activity_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('location_id')->unsigned();
            $table->integer('performed_by')->unsigned();
            $table->string('event_type', 50)->index();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('performed_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_organization_activity_logs');
        Schema::dropIfExists('gov_location_roles');
        Schema::dropIfExists('gov_ict_jurisdictions');
        Schema::dropIfExists('gov_location_profiles');
    }
}