<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Office membership redesign (docs/plans/office-membership-ux-redesign-plan.md):
 * D3 — where a leaving employee says they are going, so the receiving office can expect them.
 * D4 — an optional order or letter number recorded with an emergency change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('gov_office_memberships', 'destination_location_id')) {
            Schema::table('gov_office_memberships', function (Blueprint $t) {
                $t->unsignedInteger('destination_location_id')->nullable()->index();
            });
        }
        if (Schema::hasTable('gov_override_audit_logs')) {
            Schema::table('gov_override_audit_logs', function (Blueprint $t) {
                if (! Schema::hasColumn('gov_override_audit_logs', 'reference')) {
                    $t->string('reference', 100)->nullable();
                }
                if (! Schema::hasColumn('gov_override_audit_logs', 'details')) {
                    $t->text('details')->nullable();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('gov_office_memberships', 'destination_location_id')) {
            Schema::table('gov_office_memberships', fn (Blueprint $t) => $t->dropColumn('destination_location_id'));
        }
        foreach (['reference', 'details'] as $column) {
            if (Schema::hasColumn('gov_override_audit_logs', $column)) {
                Schema::table('gov_override_audit_logs', fn (Blueprint $t) => $t->dropColumn($column));
            }
        }
    }
};
