<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gov_committee_reminder_dismissals',function (Blueprint $table) {
            $table->unsignedInteger('user_id');
            $table->uuid('committee_id');
            $table->date('effective_to');
            $table->timestamp('dismissed_at');
            $table->primary(['user_id','committee_id','effective_to'],'committee_reminder_actor_term');
        });
    }
    public function down(): void { Schema::dropIfExists('gov_committee_reminder_dismissals'); }
};
