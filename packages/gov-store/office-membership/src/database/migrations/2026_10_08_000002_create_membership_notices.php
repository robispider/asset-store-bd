<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gov_role_assignments')) {
            Schema::create('gov_role_assignments', function (Blueprint $t) {
                $t->increments('id');
                $t->unsignedInteger('location_id');
                $t->string('role_type', 50);
                $t->unsignedInteger('assigned_user_id');
                $t->unsignedInteger('assigned_by_user_id');
                $t->string('status', 30)->default('pending');
                $t->timestamps();
                $t->index(['location_id', 'role_type', 'status']);
            });
        }
        Schema::create('gov_membership_notices', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('event_id');
            $t->unsignedInteger('user_id')->index();
            $t->unsignedInteger('location_id');
            $t->string('event_key');
            $t->text('details');
            $t->timestamp('mailed_at')->nullable();
            $t->timestamps();
            $t->unique(['event_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_membership_notices');
        // Preserve transfer history, including pre-existing installations' assignment table.
    }
};
