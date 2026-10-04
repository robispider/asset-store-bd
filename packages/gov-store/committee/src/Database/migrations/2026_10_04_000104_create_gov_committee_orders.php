<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_orders', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('committee_id')->index();
            $t->string('kind',20);
            $t->string('office_order_no',100)->nullable();
            $t->string('memo_no',150);
            $t->string('memo_no_normalized',150)->index();
            $t->string('nothi_no',150)->nullable();
            $t->date('issued_on');
            $t->string('issued_on_bangla',60)->nullable();
            $t->unsignedInteger('issuing_location_id');
            $t->string('issuing_authority_name',150);
            $t->string('issuing_authority_designation_en',150);
            $t->string('issuing_authority_designation_bn',150)->nullable();
            $t->unsignedBigInteger('corrects_order_id')->nullable();
            $t->string('attachment_disk',40)->default('committee_private');
            $t->string('attachment_path')->nullable();
            $t->char('attachment_sha256',64)->nullable();
            $t->string('attachment_mime',100)->nullable();
            $t->unsignedBigInteger('attachment_size')->nullable();
            $t->text('remarks')->nullable();
            $t->unsignedInteger('recorded_by');
            $t->timestamp('recorded_at');
            $t->unique(['issuing_location_id','memo_no_normalized','kind','committee_id'],'committee_order_memo_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_orders');
    }
};

