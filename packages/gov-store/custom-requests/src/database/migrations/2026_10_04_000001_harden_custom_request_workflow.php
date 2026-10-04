<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_request_sequences', function (Blueprint $table) {
            $table->unsignedInteger('year')->primary();
            $table->unsignedBigInteger('last_number')->default(0);
        });
        Schema::table('custom_service_requests', function (Blueprint $table) {
            // Historical rows must be reviewed before office assignment. Never infer from delivery.
            $table->unsignedInteger('office_id')->nullable()->index();
            $table->unsignedInteger('primary_decided_by')->nullable();
            $table->unsignedInteger('decided_by')->nullable()->index();
            $table->timestamp('received_at')->nullable();
            $table->foreign('office_id')->references('id')->on('locations')->onDelete('restrict');
            $table->foreign('primary_decided_by')->references('id')->on('users')->onDelete('restrict');
            $table->foreign('decided_by')->references('id')->on('users')->onDelete('restrict');
            $table->dropForeign(['requested_by']);
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('restrict');
        });
        Schema::table('custom_service_request_events', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        // Audit protection deliberately survives rollback.
        Schema::table('custom_service_requests', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropForeign(['primary_decided_by']);
            $table->dropForeign(['decided_by']);
            $table->dropColumn(['office_id', 'primary_decided_by', 'decided_by', 'received_at']);
        });
        Schema::dropIfExists('custom_request_sequences');
    }
};
