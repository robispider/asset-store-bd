<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_access_review_uses', function (Blueprint $table) {
            $table->string('token_hash', 64)->primary();
            $table->timestamp('created_at');
        });
        Schema::create('gov_access_notices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index();
            $table->string('message_key');
            $table->unsignedBigInteger('access_request_id');
            $table->timestamp('created_at');
        });
        Schema::create('gov_access_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('reference_id')->unique();
            $table->string('dedupe_key', 64)->nullable()->unique();
            $table->unsignedInteger('user_id')->nullable()->index();
            $table->unsignedInteger('location_id')->nullable()->index();
            $table->unsignedInteger('company_id')->nullable();
            $table->string('ability')->index();
            $table->text('roles');
            $table->string('outcome')->index();
            $table->text('reason')->nullable();
            $table->string('route_name')->nullable();
            $table->timestamp('created_at')->index();
        });
        Schema::create('gov_access_requests', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id')->index();
            $table->unsignedInteger('location_id')->index();
            $table->string('ability');
            $table->text('reason');
            $table->timestamp('expires_at')->nullable();
            $table->string('status')->default('pending')->index();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });
        Schema::create('gov_access_grants', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('location_id');
            $table->string('role_slug');
            $table->timestamp('expires_at')->index();
            $table->unsignedBigInteger('access_request_id');
            $table->timestamps();
            $table->unique(['user_id', 'location_id', 'role_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_access_review_uses');
        Schema::dropIfExists('gov_access_notices');
        Schema::dropIfExists('gov_access_grants');
        Schema::dropIfExists('gov_access_requests');
        Schema::dropIfExists('gov_access_events');
    }
};
