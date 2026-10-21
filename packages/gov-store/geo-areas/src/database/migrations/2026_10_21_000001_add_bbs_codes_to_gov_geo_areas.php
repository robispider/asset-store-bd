<?php

use GovStore\GeoAreas\Services\GeoAreaDatasetSync;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BBS geocode columns (plan D1) and the bundled seed: missing areas, codes and
 * mauza/village localities. Unit tests get the columns only; they seed what they need.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('gov_geo_areas')) {
            return;
        }

        Schema::table('gov_geo_areas', function (Blueprint $table) {
            if (! Schema::hasColumn('gov_geo_areas', 'bbs_code')) {
                $table->string('bbs_code', 64)->nullable()->index('gov_geo_areas_bbs_code_index');
            }
            if (! Schema::hasColumn('gov_geo_areas', 'bbs_code_legacy')) {
                $table->string('bbs_code_legacy', 32)->nullable()->index('gov_geo_areas_bbs_code_legacy_index');
            }
            if (! Schema::hasColumn('gov_geo_areas', 'code_status')) {
                $table->string('code_status', 20)->nullable()->index('gov_geo_areas_code_status_index');
            }
            if (! Schema::hasColumn('gov_geo_areas', 'code_source')) {
                $table->string('code_source', 60)->nullable();
            }
            if (! Schema::hasColumn('gov_geo_areas', 'bbs_attributes')) {
                $table->json('bbs_attributes')->nullable();
            }
        });

        if (! app()->runningUnitTests()) {
            app(GeoAreaDatasetSync::class)->run();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('gov_geo_areas')) {
            return;
        }

        // Remove only seeded areas that nothing references; bundled 9,111 areas stay.
        $referenced = collect([
            ['gov_location_profiles', 'geo_area_id'], ['gov_ict_jurisdictions', 'geo_area_id'],
            ['gov_tracking_fact_deliveries', 'geo_area_id'], ['gov_user_onboardings', 'geo_area_id'],
        ])->filter(fn ($t) => Schema::hasTable($t[0]))
            ->flatMap(fn ($t) => DB::table($t[0])->whereNotNull($t[1])->distinct()->pluck($t[1]))
            ->map(fn ($id) => (int) $id)->unique()->all();
        DB::table('gov_geo_areas')->where('GeoAreaId', '>=', 1000000)->whereNotIn('GeoAreaId', $referenced)->delete();
        DB::table('gov_geo_areas')->whereBetween('GeoAreaId', [9359, 999999])->whereNotIn('GeoAreaId', $referenced)->delete();

        Schema::table('gov_geo_areas', function (Blueprint $table) {
            foreach (['bbs_code', 'bbs_code_legacy', 'code_status'] as $column) {
                if (Schema::hasIndex('gov_geo_areas', "gov_geo_areas_{$column}_index")) {
                    $table->dropIndex("gov_geo_areas_{$column}_index");
                }
            }
        });
        Schema::table('gov_geo_areas', function (Blueprint $table) {
            $columns = array_values(array_filter(GeoAreaDatasetSync::CODE_COLUMNS,
                fn ($column) => Schema::hasColumn('gov_geo_areas', $column)));
            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
