<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_health', function (Blueprint $t) {
            $t->uuid('committee_id')->primary();
            $t->string('status',15);
            $t->json('issues');
            $t->timestamp('computed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_health');
    }
};

