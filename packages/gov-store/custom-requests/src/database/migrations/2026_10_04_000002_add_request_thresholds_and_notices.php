<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_approval_policies', function (Blueprint $table) {
            $table->unsignedInteger('threshold_qty')->nullable();
            $table->decimal('threshold_value', 15, 2)->nullable();
        });
        Schema::create('custom_request_notices', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('request_id');
            $table->unsignedInteger('user_id');
            $table->string('event_key', 50);
            $table->string('deduplication_key', 100)->unique();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('emailed_at')->nullable();
            $table->timestamps();
            $table->foreign('request_id')->references('id')->on('custom_service_requests')->onDelete('restrict');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_request_notices');
        Schema::table('gov_approval_policies', fn (Blueprint $table) => $table->dropColumn(['threshold_qty', 'threshold_value']));
    }
};
