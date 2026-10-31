<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_reviewed_operations', function (Blueprint $table) {
            $table->id();
            $table->string('review_hash', 64)->unique();
            $table->unsignedInteger('actor_id');
            $table->string('ability');
            $table->string('plan_hash', 64);
            $table->string('status')->default('queued');
            $table->text('reason');
            $table->unsignedInteger('processed')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->string('reference_id')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_reviewed_operations');
    }
};
