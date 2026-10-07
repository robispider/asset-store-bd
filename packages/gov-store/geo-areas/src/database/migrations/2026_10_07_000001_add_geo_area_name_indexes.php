<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('gov_geo_areas')) {
            return;
        }

        Schema::table('gov_geo_areas', function (Blueprint $table) {
            if (!Schema::hasIndex('gov_geo_areas', 'gov_geo_areas_en_name_index')) {
                $table->index('en_name', 'gov_geo_areas_en_name_index');
            }
            if (!Schema::hasIndex('gov_geo_areas', 'gov_geo_areas_bn_name_index')) {
                $table->index('bn_name', 'gov_geo_areas_bn_name_index');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('gov_geo_areas')) {
            return;
        }

        Schema::table('gov_geo_areas', function (Blueprint $table) {
            if (Schema::hasIndex('gov_geo_areas', 'gov_geo_areas_en_name_index')) {
                $table->dropIndex('gov_geo_areas_en_name_index');
            }
            if (Schema::hasIndex('gov_geo_areas', 'gov_geo_areas_bn_name_index')) {
                $table->dropIndex('gov_geo_areas_bn_name_index');
            }
        });
    }
};
