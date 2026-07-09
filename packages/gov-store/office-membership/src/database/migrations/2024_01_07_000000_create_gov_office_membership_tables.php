<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGovOfficeMembershipTables extends Migration
{
    public function up()
    {
        Schema::dropIfExists('gov_override_audit_logs');
        Schema::dropIfExists('gov_role_assignments');
        Schema::dropIfExists('gov_office_memberships');

        // 1. Office Memberships (Maps Users to Multiple Offices with a specific Status)
        Schema::create('gov_office_memberships', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->integer('location_id')->unsigned()->index();
            $table->boolean('is_default')->default(false);
            $table->string('status', 30)->default('active')->index(); // 'active', 'release_requested', 'released', 'suspended'
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            
            // A user can only have one membership record per physical location
            $table->unique(['user_id', 'location_id']);
        });

        // 2. Generic Role Assignments (The 3-way handshake engine)
        Schema::create('gov_role_assignments', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id')->unsigned();
            $table->string('role_type', 50); // e.g. 'storekeeper', 'primary_approver'
            $table->integer('assigned_user_id')->unsigned(); // Person receiving
            $table->integer('assigned_by_user_id')->unsigned(); // Person delegating/outgoing
            $table->string('status', 30)->default('pending'); // 'pending', 'accepted', 'rejected', 'completed'
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('assigned_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('assigned_by_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 3. Superadmin Override Audit Logs (Mandatory Compliance Log)
        Schema::create('gov_override_audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('target_user_id')->unsigned();
            $table->string('override_type', 50); // e.g., 'force_release', 'force_claim', 'force_role_swap'
            $table->text('reason'); // Mandatory justification
            $table->integer('executed_by')->unsigned(); // The Superadmin
            $table->integer('old_location_id')->unsigned()->nullable();
            $table->integer('new_location_id')->unsigned()->nullable();
            $table->timestamps();

            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('executed_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_override_audit_logs');
        Schema::dropIfExists('gov_role_assignments');
        Schema::dropIfExists('gov_office_memberships');
    }
}