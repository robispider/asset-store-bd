<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_ledger', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('lineage_id')->index();
            $t->uuid('committee_id')->nullable()->index();
            $t->string('event_type',50);
            $t->json('payload');
            $t->unsignedBigInteger('order_id')->nullable();
            $t->unsignedInteger('actor_id')->nullable();
            $t->json('actor_roles');
            $t->unsignedInteger('actor_location_id')->nullable();
            $t->text('reason')->nullable();
            $t->timestamp('occurred_at',6);
            $t->char('prev_hash',64);
            $t->char('hash',64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_ledger');
    }
};

