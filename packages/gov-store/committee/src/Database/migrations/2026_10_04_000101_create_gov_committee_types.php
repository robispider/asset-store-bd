<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_types', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code',20);
            $t->unsignedInteger('owner_company_id')->nullable();
            $t->unsignedInteger('derived_from_type_id')->nullable();
            $t->string('name_en',150);
            $t->string('name_bn',150);
            $t->text('description_en')->nullable();
            $t->text('description_bn')->nullable();
            $t->string('category',30);
            $t->string('default_term_basis',30);
            $t->unsignedSmallInteger('default_term_months')->nullable();
            $t->json('allowed_scope_types');
            $t->boolean('allow_concurrent')->default(false);
            $t->json('composition_policy');
            $t->unsignedInteger('policy_version')->default(1);
            $t->boolean('is_active')->default(false);
            $t->unsignedInteger('created_by')->nullable();
            $t->unsignedInteger('updated_by')->nullable();
            $t->unique(['owner_company_id','code']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_types');
    }
};

