<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_scopes', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('committee_id')->index();
            $t->string('scope_type',40);
            $t->string('scope_id',64);
            $t->string('scope_label_snapshot');
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->unsignedBigInteger('order_id');
            $t->unsignedInteger('assigned_by');
            $t->index(['scope_type','scope_id','effective_from'],'committee_scope_lookup');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_scopes');
    }
};

