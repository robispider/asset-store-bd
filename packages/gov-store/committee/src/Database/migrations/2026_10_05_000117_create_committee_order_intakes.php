<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gov_committee_order_intakes',function (Blueprint $t) {
            $t->unsignedInteger('user_id'); $t->unsignedInteger('location_id'); $t->unsignedInteger('company_id');
            $t->text('payload'); $t->text('attachment')->nullable(); $t->unsignedInteger('revision')->default(1); $t->timestamp('updated_at');
            $t->primary(['user_id','location_id','company_id']);
        });
    }
    public function down(): void { Schema::dropIfExists('gov_committee_order_intakes'); }
};
