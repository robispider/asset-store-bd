<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gov_location_profiles') && !Schema::hasColumn('gov_location_profiles', 'office_type')) {
            Schema::table('gov_location_profiles', function (Blueprint $table) {
                $table->string('office_type', 40)->default('default');
            });
        }

        $legacyTable = 'gov_location_roles';
        $targetTable = 'gov_office_responsibilities';
        if (!Schema::hasTable($legacyTable) || !Schema::hasTable($targetTable)
            || !Schema::hasTable('gov_office_memberships') || !Schema::hasTable('users')
            || !Schema::hasTable('locations') || !Schema::hasColumn('gov_office_memberships', 'status')) {
            return;
        }

        $roles = [
            'primary_approver_id' => 'primary_approver',
            'final_approver_id' => 'final_approver',
            'storekeeper_id' => 'storekeeper',
        ];
        foreach ($roles as $column => $role) {
            if (!Schema::hasColumn($legacyTable, $column)) {
                continue;
            }

            DB::table($legacyTable)
                ->whereNotNull($column)
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($column, $role, $targetTable) {
                    $now = now();
                    $records = [];
                    foreach ($rows as $row) {
                        if (!DB::table('locations')->where('id', $row->location_id)->exists()
                            || !DB::table('users')->where('id', $row->{$column})->exists()) {
                            continue;
                        }

                        $userQuery = DB::table('users')->where('id', $row->{$column});
                        if (Schema::hasColumn('users', 'deleted_at')) {
                            $userQuery->whereNull('deleted_at');
                        }
                        if (Schema::hasColumn('users', 'activated')) {
                            $userQuery->where('activated', 1);
                        }
                        if (!$userQuery->exists()) {
                            continue;
                        }

                        $membershipQuery = DB::table('gov_office_memberships')
                            ->where('location_id', $row->location_id)
                            ->where('user_id', $row->{$column})
                            ->where('status', 'active');
                        if (Schema::hasColumn('gov_office_memberships', 'valid_until')) {
                            $membershipQuery->where(function ($query) {
                                $query->whereNull('valid_until')->orWhereDate('valid_until', '>=', now()->toDateString());
                            });
                        }
                        if (!$membershipQuery->exists()) {
                            continue;
                        }

                        $records[] = [
                            'location_id' => $row->location_id,
                            'user_id' => $row->{$column},
                            'role_slug' => $role,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($records) {
                        DB::table($targetTable)->insertOrIgnore($records);
                    }
                });
        }
    }

    public function down(): void
    {
        // Keep the additive office_type column and copied responsibilities on rollback.
        // Both may have been edited after this migration; dropping them would lose data.
    }
};
