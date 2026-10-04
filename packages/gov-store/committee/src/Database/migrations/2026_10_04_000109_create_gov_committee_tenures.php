<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_tenures', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('seat_id')->index();
            $t->uuid('committee_id')->index();
            $t->unsignedInteger('user_id')->nullable()->index();
            $t->unsignedBigInteger('external_member_id')->nullable();
            $t->string('name_snapshot_en',200);
            $t->string('name_snapshot_bn',200);
            $t->string('designation_snapshot_en',200);
            $t->string('designation_snapshot_bn',200);
            $t->unsignedInteger('home_location_id_snapshot')->nullable();
            $t->unsignedInteger('home_company_id_snapshot')->nullable();
            $t->boolean('is_external');
            $t->string('identified_via',20);
            $t->date('from_date');
            $t->date('to_date')->nullable();
            $t->string('status',15)->default('ACTIVE');
            $t->unsignedBigInteger('appointment_order_id');
            $t->unsignedBigInteger('release_order_id')->nullable();
            $t->string('release_reason',20)->nullable();
            $t->text('release_note')->nullable();
            $t->unsignedBigInteger('succeeded_by_tenure_id')->nullable();
            $t->unsignedBigInteger('corrects_tenure_id')->nullable();
            $t->string('declaration_status',15);
            $t->date('declaration_filed_on')->nullable();
            $t->string('declaration_attachment_path')->nullable();
            $t->char('declaration_attachment_sha256',64)->nullable();
            $t->text('remarks')->nullable();
            $t->unsignedInteger('created_by');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_tenures');
    }
};

