<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateGovGeoAreasTable extends Migration
{
    public function up()
    {
        // 1. Disable constraints and recreate table
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('gov_geo_areas');
        Schema::enableForeignKeyConstraints();

        Schema::create('gov_geo_areas', function (Blueprint $table) {
            $table->integer('GeoAreaId')->unsigned()->primary();
            
            // Replaced unique() with nullable()->index() to prevent empty-string index clashes
            $table->string('hid', 255)->nullable()->index();
            
            $table->string('geo_type', 30)->index();
            $table->integer('parent_geo_code')->nullable();
            $table->integer('geo_code');
            $table->string('bn_name', 255);
            $table->string('domain', 255)->nullable();
            $table->string('en_name', 255);
            $table->integer('GeoLevel')->index();
            $table->timestamps();
        });

        // 2. Automatically execute the bulk CSV data importer
        $this->importGeographicalDataset();
    }

    public function down()
    {
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('gov_geo_areas');
        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reads the geo_areas.csv dataset dynamically and performs fast bulk database inserts.
     */
    private function importGeographicalDataset()
    {
        $csvPath = __DIR__ . '/../data/geo_areas.csv';

        if (!file_exists($csvPath)) {
            return;
        }

        if (($handle = fopen($csvPath, 'r')) !== false) {
            $insertBatch = [];
            $chunkSize = 250;

            while (($row = fgetcsv($handle, 1000, ",")) !== false) {
                
                if (empty($row) || count($row) < 9) {
                    continue;
                }

                if (!is_numeric($row[0])) {
                    continue;
                }

                // Clean empty or missing strings, converting them to true NULLs to satisfy database rules
                $hid = trim($row[1]);
                $domain = trim($row[6]);

                $insertBatch[] = [
                    'GeoAreaId'       => (int)$row[0],
                    'hid'             => (empty($hid) || $hid === '?') ? null : $hid,
                    'geo_type'        => trim($row[2]),
                    'parent_geo_code' => !empty($row[3]) ? (int)$row[3] : null,
                    'geo_code'        => (int)$row[4],
                    'bn_name'         => trim($row[5]),
                    'domain'          => (empty($domain) || $domain === '?') ? null : $domain,
                    'en_name'         => trim($row[7]),
                    'GeoLevel'        => (int)$row[8],
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ];

                if (count($insertBatch) >= $chunkSize) {
                    DB::table('gov_geo_areas')->insert($insertBatch);
                    $insertBatch = [];
                }
            }

            if (count($insertBatch) > 0) {
                DB::table('gov_geo_areas')->insert($insertBatch);
            }

            fclose($handle);
        }
    }
}