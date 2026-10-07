<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_seats', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->uuid('committee_id')->index();
            $t->string('seat_role_code',40);
            $t->unsignedSmallInteger('seat_no');
            $t->string('holder_kind',10);
            $t->string('post_title_en',200)->nullable();
            $t->string('post_title_bn',200)->nullable();
            $t->unsignedInteger('post_location_id')->nullable();
            $t->boolean('is_required')->default(false);
            $t->unsignedInteger('created_by');
            $t->unique(['committee_id','seat_no']);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_seats');
    }
};

