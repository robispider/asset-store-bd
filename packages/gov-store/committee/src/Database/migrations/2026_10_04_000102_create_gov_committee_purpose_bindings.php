<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_purpose_bindings', function (Blueprint $t) {
            $t->increments('id');
            $t->string('purpose_code',80)->index();
            $t->unsignedInteger('owner_company_id')->nullable();
            $t->unsignedInteger('committee_type_id');
            $t->unsignedSmallInteger('priority')->default(0);
            $t->boolean('allow_ancestor_fallback')->default(false);
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('changed_by');
            $t->text('change_reason');
            $t->unique(['purpose_code','owner_company_id','committee_type_id'],'committee_binding_unique');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_purpose_bindings');
    }
};

