<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_reviews', function (Blueprint $t) {
            $t->char('token_hash',64)->primary();
            $t->unsignedInteger('actor_id');
            $t->string('target');
            $t->json('proposal');
            $t->char('configuration_hash',64);
            $t->timestamp('expires_at');
            $t->timestamp('consumed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_reviews');
    }
};

