<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGovOfficeMembershipTables extends Migration
{
    public function up()
    {
        // 1. Drop older versions to ensure clean structure mapping
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('gov_override_audit_logs');
        Schema::dropIfExists('gov_role_handshakes');
        Schema::dropIfExists('gov_role_assignments');
        Schema::dropIfExists('gov_office_memberships');
        Schema::enableForeignKeyConstraints();

        // 2. Active Office Memberships (Decouples user identity from physical building context)
        Schema::create('gov_office_memberships', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->integer('location_id')->unsigned()->index();
            $table->boolean('is_home_office')->default(false); // Declares if this is their primary HR home base
            $table->string('status', 30)->default('active')->index(); // active, suspended, inactive
            $table->date('valid_until')->nullable(); // Supports temporary active acting designations
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->unique(['user_id', 'location_id']);
        });

        // 3. Operational Role Handshakes (Proposals and Acceptances transitions)
        Schema::create('gov_role_handshakes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id')->unsigned();
            $table->string('role_type', 50); // e.g., 'storekeeper', 'primary_approver'
            $table->integer('outgoing_user_id')->unsigned(); // Person handing over
            $table->integer('incoming_user_id')->unsigned(); // Person receiving
            $table->string('status', 30)->default('pending')->index(); // pending, accepted, rejected, cancelled
            $table->timestamps();

            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('outgoing_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('incoming_user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 4. Emergency Override Audit Logs (Compliance Preservation)
        Schema::create('gov_override_audit_logs', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('target_user_id')->unsigned();
            $table->string('override_type', 50); // e.g., 'force_release', 'force_role_swap'
            $table->text('reason'); // Mandatory justification
            $table->integer('executed_by')->unsigned();
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
        Schema::dropIfExists('gov_role_handshakes');
        Schema::dropIfExists('gov_office_memberships');
    }
}