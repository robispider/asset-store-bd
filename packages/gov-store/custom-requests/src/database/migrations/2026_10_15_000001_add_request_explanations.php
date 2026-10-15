<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * UX redesign decisions D2–D4 (docs/plans/custom-requests-ux-redesign-plan.md). Additive and nullable:
 * existing rows keep working and simply show less explanation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('custom_service_requests', function (Blueprint $table) {
            // D2: requests sent together from one draft share a batch, so each can name the others.
            $table->uuid('submission_batch')->nullable()->index();
            // D3: why the approval rule applied when the request was sent (source, threshold reached).
            $table->json('resolved_reason')->nullable();
        });
        Schema::table('gov_approval_policies', function (Blueprint $table) {
            // D4: rules apply to every office, so the page says who last changed one.
            $table->unsignedInteger('updated_by')->nullable();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('gov_approval_policies', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });
        Schema::table('custom_service_requests', function (Blueprint $table) {
            $table->dropIndex(['submission_batch']);
            $table->dropColumn(['submission_batch', 'resolved_reason']);
        });
    }
};
