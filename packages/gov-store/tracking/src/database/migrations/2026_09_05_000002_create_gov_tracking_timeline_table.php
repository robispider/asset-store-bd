<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_tracking_timeline');

        Schema::create('gov_tracking_timeline', function (Blueprint $table) {
            $table->id();
            $table->foreignId('initiative_id')->constrained('gov_initiatives')->onDelete('cascade');
            $table->string('event_type'); 
            $table->text('description');
            $table->unsignedInteger('actor_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->foreign('actor_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_timeline');
    }
};