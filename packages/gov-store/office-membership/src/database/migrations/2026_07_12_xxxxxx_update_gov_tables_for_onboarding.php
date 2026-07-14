<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateGovTablesForOnboarding extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // 1. Create the dedicated verification tokens table (Temporary credentials)
        Schema::create('gov_employee_verification_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('user_id')->unsigned()->index();
            $table->string('token', 10)->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });

        // 2. Append approval metadata to memberships
        Schema::table('gov_office_memberships', function (Blueprint $table) {
            $table->integer('approved_by_user_id')->unsigned()->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->string('approval_note', 255)->nullable()->after('approved_at');
            
            $table->foreign('approved_by_user_id', 'fk_gov_mem_approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Append mass-invitation columns to location profiles
        Schema::table('gov_location_profiles', function (Blueprint $table) {
            $table->string('invitation_code', 15)->unique()->nullable()->after('lifecycle_status');
            $table->timestamp('invitation_code_created_at')->nullable()->after('invitation_code');
            $table->timestamp('invitation_code_expires_at')->nullable()->after('invitation_code_created_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('gov_location_profiles', function (Blueprint $table) {
            $table->dropColumn(['invitation_code', 'invitation_code_created_at', 'invitation_code_expires_at']);
        });

        Schema::table('gov_office_memberships', function (Blueprint $table) {
            $table->dropForeign('fk_gov_mem_approved_by');
            $table->dropColumn(['approved_by_user_id', 'approved_at', 'approval_note']);
        });

        Schema::dropIfExists('gov_employee_verification_tokens');
    }
}