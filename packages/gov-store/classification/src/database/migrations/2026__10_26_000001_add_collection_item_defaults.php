<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The double underscore orders this after the existing 2026__08_04 collection migration.
    public function up(): void
    {
        Schema::table('gov_catalog_collections', function (Blueprint $table) {
            $table->string('key', 100)->nullable()->unique();
            $table->string('name_bn')->nullable();
            $table->text('description_bn')->nullable();
        });
        Schema::table('gov_catalog_collection_nodes', function (Blueprint $table) {
            $table->string('category_type', 20)->nullable();
            $table->string('section', 50)->nullable();
            $table->string('unit', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gov_catalog_collection_nodes', fn (Blueprint $table) => $table->dropColumn(['category_type', 'section', 'unit']));
        Schema::table('gov_catalog_collections', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->dropColumn(['key', 'name_bn', 'description_bn']);
        });
    }
};
