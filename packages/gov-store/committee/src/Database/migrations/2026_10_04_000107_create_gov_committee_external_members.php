<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_external_members', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedInteger('owner_company_id')->index();
            $t->string('full_name_en',150);
            $t->string('full_name_bn',150);
            $t->string('designation_en',150);
            $t->string('designation_bn',150);
            $t->string('organization_name_en',200);
            $t->string('organization_name_bn',200);
            $t->string('organization_kind',30);
            $t->unsignedInteger('home_location_id')->nullable();
            $t->unsignedInteger('home_company_id')->nullable();
            $t->string('mobile',30)->nullable();
            $t->string('email')->nullable();
            $t->boolean('expected_to_onboard')->default(false);
            $t->unsignedInteger('linked_user_id')->nullable();
            $t->timestamp('linked_at')->nullable();
            $t->unsignedInteger('linked_by')->nullable();
            $t->unsignedInteger('created_by');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_external_members');
    }
};

