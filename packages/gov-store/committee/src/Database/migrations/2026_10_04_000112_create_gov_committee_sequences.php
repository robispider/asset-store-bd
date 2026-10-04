<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_sequences', function (Blueprint $t) {
            $t->unsignedInteger('owner_location_id');
            $t->char('fiscal_year',7);
            $t->unsignedInteger('last_no')->default(0);
            $t->primary(['owner_location_id','fiscal_year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_sequences');
    }
};

