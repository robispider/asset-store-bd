<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_active_slots', function (Blueprint $t) {
            $t->unsignedInteger('committee_type_id');
            $t->string('scope_type',40);
            $t->string('scope_id',64);
            $t->uuid('committee_id')->index();
            $t->primary(['committee_type_id','scope_type','scope_id'],'committee_active_slot_pk');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_active_slots');
    }
};

