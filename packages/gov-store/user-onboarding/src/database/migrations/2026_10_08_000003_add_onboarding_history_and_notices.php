<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_user_onboardings', function (Blueprint $t) {
            $t->unsignedInteger('creator_user_id')->nullable()->change();
            $t->unsignedInteger('owner_id')->nullable()->change();
            $t->unsignedInteger('managed_location_id')->nullable();
            $t->index(['owner_id', 'status']);
        });
        Schema::create('gov_onboarding_events', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('event_id')->unique();
            $t->unsignedInteger('onboarding_id')->index();
            $t->unsignedInteger('actor_id')->nullable();
            $t->string('event_key', 30);
            $t->text('reason')->nullable();
            $t->text('before_state');
            $t->text('after_state');
            $t->timestamp('created_at');
        });
        Schema::create('gov_onboarding_notices', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('event_id');
            $t->unsignedInteger('onboarding_id')->index();
            $t->unsignedInteger('user_id')->index();
            $t->string('event_key', 30);
            $t->unsignedInteger('location_id')->nullable();
            $t->timestamp('created_at');
            $t->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        // Preserve history and SYSTEM records on rollback; null owners cannot become non-null safely.
    }
};
