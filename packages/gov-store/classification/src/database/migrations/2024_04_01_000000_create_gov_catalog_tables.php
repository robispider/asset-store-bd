<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Splits tables into Reference (read-only, overwritten by imports) and Operational (editable, never overwritten).
     * All operational data soft-links to reference nodes via code rather than database ID.
     */
    public function up()
    {
        // ==========================================
        // REFERENCE DATA (Read-Only, Overwritten by Imports)
        // ==========================================

        Schema::create('gov_catalog_nodes', function (Blueprint $table) {
            $table->id();
            $table->string('scheme', 50)->default('UNSPSC');
            $table->string('version', 50);
            $table->string('code', 50)->unique(); // Immutable anchor
            $table->string('parent_code', 50)->nullable()->index();
            $table->unsignedTinyInteger('level'); // 1=Seg, 2=Fam, 3=Class, 4=Comm
            $table->string('title_en', 255);
            $table->string('hid', 255)->index(); // Materialized Path
            $table->boolean('is_selectable')->default(true);
            $table->timestamps();
        });

        Schema::create('gov_catalog_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->text('definition_en')->nullable();
            $table->timestamps();

            $table->foreign('code')->references('code')->on('gov_catalog_nodes')->onDelete('cascade');
        });

        // ==========================================
        // OPERATIONAL DATA (Editable, Never Overwritten)
        // ==========================================

        Schema::create('gov_catalog_enrichments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // Soft-link to survive truncates/re-imports
            $table->string('title_bn', 255)->nullable();
            $table->text('definition_bn')->nullable();
            $table->text('local_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('gov_catalog_synonyms', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->index();
            $table->string('language', 10);
            $table->string('synonym', 255)->index();
            $table->string('type', 50)->default('common'); // 'official', 'common', 'alias'
            $table->timestamps();
        });

        Schema::create('gov_catalog_snipe_mappings', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique(); // One UNSPSC code maps to ONE Snipe Category
            $table->unsignedInteger('category_id')->index(); // Multiple codes can map to SAME Category
            $table->timestamps();
        });

        Schema::create('gov_catalog_import_history', function (Blueprint $table) {
            $table->id();
            $table->string('scheme', 50);
            $table->string('version', 50);
            $table->string('filename', 255);
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->decimal('duration_seconds', 8, 2)->default(0);
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('imported_at')->useCurrent();
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_catalog_import_history');
        Schema::dropIfExists('gov_catalog_snipe_mappings');
        Schema::dropIfExists('gov_catalog_synonyms');
        Schema::dropIfExists('gov_catalog_enrichments');
        Schema::dropIfExists('gov_catalog_definitions');
        Schema::dropIfExists('gov_catalog_nodes');
    }
};
