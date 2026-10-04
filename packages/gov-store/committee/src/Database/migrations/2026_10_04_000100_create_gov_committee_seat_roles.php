<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_committee_seat_roles', function (Blueprint $t) {
            $t->increments('id');
            $t->string('code',40)->unique();
            $t->string('name_en',100);
            $t->string('name_bn',100);
            $t->boolean('is_presiding')->default(false);
            $t->boolean('is_secretary')->default(false);
            $t->boolean('counts_toward_strength')->default(true);
            $t->smallInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_committee_seat_roles');
    }
};

