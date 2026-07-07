<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGovApprovalPolicyTables extends Migration
{
    public function up()
    {
        // 1. Create Location Roles Table
        Schema::create('gov_location_roles', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id')->unsigned()->unique();
            
            // Primary Approver & Delegate
            $table->integer('primary_approver_id')->unsigned();
            $table->integer('primary_delegate_id')->unsigned()->nullable();
            $table->date('primary_delegate_until')->nullable();
            
            // Final Approver & Delegate
            $table->integer('final_approver_id')->unsigned()->nullable();
            $table->integer('final_delegate_id')->unsigned()->nullable();
            $table->date('final_delegate_until')->nullable();
            
            // Location Storekeeper
            $table->integer('storekeeper_id')->unsigned();
            
            $table->timestamps();

            // Foreign Keys to Snipe-IT's core tables
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('primary_approver_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('primary_delegate_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('final_approver_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('final_delegate_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('storekeeper_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 2. Create Polymorphic Policies Table
        Schema::create('gov_approval_policies', function (Blueprint $table) {
            $table->increments('id');
            $table->string('target_type', 50); // e.g., 'category', 'consumable', 'asset_model'
            $table->integer('target_id')->unsigned();
            $table->string('policy_name', 30)->default('PRIMARY_ONLY'); // AUTO_APPROVE, PRIMARY_ONLY, PRIMARY_AND_FINAL
            $table->timestamps();

            $table->index(['target_type', 'target_id']);
        });

        // 3. Alter Parent Service Requests Table to track the active policy and pending approver
        Schema::table('custom_service_requests', function (Blueprint $table) {
            $table->string('resolved_policy', 30)->default('PRIMARY_ONLY')->after('request_type')->index();
            $table->integer('assigned_approver_id')->unsigned()->nullable()->after('approved_by');

            $table->foreign('assigned_approver_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        // Remove foreign key before altering table back
        Schema::table('custom_service_requests', function (Blueprint $table) {
            $table->dropForeign(['assigned_approver_id']);
            $table->dropColumn(['resolved_policy', 'assigned_approver_id']);
        });

        Schema::dropIfExists('gov_approval_policies');
        Schema::dropIfExists('gov_location_roles');
    }
}