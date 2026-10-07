<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committees', function (Blueprint $t) {
            $t->uuid('id')->primary();
            $t->uuid('lineage_id')->index();
            $t->unsignedSmallInteger('version_no')->default(1);
            $t->uuid('supersedes_id')->nullable();
            $t->string('committee_number',50)->unique();
            $t->unsignedInteger('committee_type_id');
            $t->unsignedInteger('type_policy_version')->default(1);
            $t->json('policy_snapshot')->nullable();
            $t->string('name_en',200);
            $t->string('name_bn',200);
            $t->text('terms_of_reference')->nullable();
            $t->unsignedInteger('owner_company_id');
            $t->unsignedInteger('owner_location_id');
            $t->string('status',20)->index();
            $t->string('term_basis',30);
            $t->date('effective_from');
            $t->date('effective_to')->nullable();
            $t->char('fiscal_year',7)->nullable();
            $t->date('ended_on')->nullable();
            $t->string('end_reason',30)->nullable();
            $t->unsignedBigInteger('constitution_order_id')->nullable();
            $t->timestamp('activated_at')->nullable();
            $t->unsignedInteger('activated_by')->nullable();
            $t->unsignedInteger('lock_version')->default(0);
            $t->unsignedInteger('created_by');
            $t->unique(['lineage_id','version_no']);
            $t->index(['owner_location_id','status']);
            $t->index(['committee_type_id','status']);
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committees');
    }
};

