<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Receipt-unit identity and tag evidence on the existing bridge. Existing rows keep NULLs (their history
 * is preserved as it is). Each unique key is added only when the existing rows already satisfy it;
 * otherwise `asset-tags:preflight` lists the duplicates for reconciliation and enablement stays blocked (G2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_asset_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('gov_asset_registrations', 'unit_index')) {
                $table->unsignedInteger('unit_index')->nullable()->after('intake_item_id');
            }
            if (! Schema::hasColumn('gov_asset_registrations', 'format_version')) {
                $table->string('format_version', 50)->nullable()->after('serial_number');
            }
            if (! Schema::hasColumn('gov_asset_registrations', 'gov_code')) {
                $table->char('gov_code', 6)->nullable()->after('format_version');
            }
            if (! Schema::hasColumn('gov_asset_registrations', 'tag_snapshot')) {
                $table->json('tag_snapshot')->nullable()->after('gov_code');
            }
        });

        $indexes = collect(Schema::getIndexes('gov_asset_registrations'))->pluck('name')->all();
        $unique = [
            'registration_asset_tag' => ['asset_tag'],
            'registration_source_unit' => ['intake_item_id', 'unit_index'],
            'registration_asset' => ['asset_id'],
        ];
        foreach ($unique as $name => $columns) {
            if (in_array($name, $indexes, true)) {
                continue;
            }
            $duplicates = DB::table('gov_asset_registrations')->select($columns)
                ->when(in_array('unit_index', $columns, true), fn ($q) => $q->whereNotNull('unit_index'))
                ->groupBy($columns)->havingRaw('count(*) > 1')->exists();
            if ($duplicates) {
                Log::warning("gov_asset_registrations: {$name} not applied because existing rows repeat it. Run asset-tags:preflight.");

                continue;
            }
            Schema::table('gov_asset_registrations', fn (Blueprint $table) => $table->unique($columns, $name));
        }
        if (! in_array('registration_finance_code', $indexes, true)) {
            Schema::table('gov_asset_registrations', fn (Blueprint $table) => $table->index('gov_code', 'registration_finance_code'));
        }

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE gov_asset_registrations MODIFY gov_code CHAR(6) CHARACTER SET ascii COLLATE ascii_bin NULL');
            $this->restrictForeignKeys();
        }
    }

    /** Issued tags outlive their receipt rows: CASCADE becomes RESTRICT, and gov_code must be a Finance item. */
    private function restrictForeignKeys(): void
    {
        $foreign = collect(Schema::getForeignKeys('gov_asset_registrations'));
        $intake = $foreign->first(fn ($key) => $key['columns'] === ['intake_item_id']);
        if ($intake && strtolower((string) $intake['on_delete']) !== 'restrict') {
            Schema::table('gov_asset_registrations', function (Blueprint $table) use ($intake) {
                $table->dropForeign($intake['name']);
                $table->foreign('intake_item_id', 'registration_intake_item')->references('id')->on('gov_document_items')->restrictOnDelete();
            });
        }
        if (Schema::hasTable('gov_finance_items') && ! $foreign->contains(fn ($key) => $key['columns'] === ['gov_code'])) {
            Schema::table('gov_asset_registrations', fn (Blueprint $table) => $table->foreign('gov_code', 'registration_gov_code')
                ->references('gov_code')->on('gov_finance_items')->restrictOnDelete());
        }
    }

    public function down(): void
    {
        // Evidence columns are never dropped automatically: issued tags must stay explainable.
    }
};
