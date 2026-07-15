<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Creates 6 tables:
     * - gov_classification_nodes: Core tree (authoritative catalog)
     * - gov_classification_metadata: Node definitions and source info
     * - gov_classification_synonyms: Multilingual synonym mappings
     * - gov_classification_external_mappings: External standard crosswalks (CGA, HS, etc.)
     * - gov_classification_snipe_mappings: Bridge to Snipe-IT categories (Many-to-One)
     * - gov_classification_import_history: Import audit trail
     */
    public function up()
    {
        // 1. Core Tree (The Authoritative Catalog)
        Schema::create('gov_classification_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            
            $table->string('scheme', 50)->default('UNSPSC');
            $table->string('version', 50); // e.g., 'UNv260801'
            $table->string('code', 50); 
            
            // 1=Segment, 2=Family, 3=Class, 4=Commodity
            $table->unsignedTinyInteger('level'); 
            
            $table->string('title_en', 255);
            $table->string('title_bn', 255)->nullable();
            
            $table->string('hid', 255)->index(); // Materialized path (Rebuildable, not source of truth)
            $table->boolean('is_selectable')->default(true);
            $table->timestamps();

            // Future-proofing: Same code can exist across different versions/schemes
            $table->unique(['scheme', 'version', 'code'], 'idx_unique_node');
            $table->foreign('parent_id')->references('id')->on('gov_classification_nodes')->onDelete('cascade');
        });

        // 2. Metadata (Extended, but kept flat)
        Schema::create('gov_classification_metadata', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('gov_classification_nodes')->onDelete('cascade');
            $table->text('definition_en')->nullable();
            $table->text('definition_bn')->nullable();
            $table->string('source', 100)->nullable();
            $table->string('reference_url', 255)->nullable();
            $table->string('status', 50)->default('active');
            $table->date('effective_date')->nullable();
            $table->timestamps();

            $table->unique('node_id');
        });

        // 3. Synonyms (Multilingual & Typed)
        Schema::create('gov_classification_synonyms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('gov_classification_nodes')->onDelete('cascade');
            $table->string('language', 10);
            $table->string('synonym', 255)->index();
            $table->string('type', 50)->default('common'); // 'official', 'common', 'alias'
            $table->timestamps();

            $table->index(['node_id', 'language']);
        });

        // 4. External Standards Mapping (e.g., CGA, HS Code)
        Schema::create('gov_classification_external_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('node_id')->constrained('gov_classification_nodes')->onDelete('cascade');
            $table->string('mapping_scheme', 50);
            $table->string('external_id', 100)->nullable();
            $table->string('external_code', 100)->nullable();
            $table->string('external_name', 255)->nullable();
            $table->timestamps();
            
            $table->index(['mapping_scheme', 'external_code']);
        });

        // 5. Native Snipe-IT Integration Bridge (Many-to-One Support)
        Schema::create('gov_classification_snipe_mappings', function (Blueprint $table) {
            // node_id is unique: One UNSPSC code can only map to ONE Snipe-IT Category
            $table->foreignId('node_id')->unique()->constrained('gov_classification_nodes')->onDelete('cascade');
            // But category_id is just indexed: MULTIPLE UNSPSC codes can map to the SAME Snipe-IT Category
            $table->unsignedInteger('category_id')->index(); 
            $table->timestamps();

            $table->index('category_id');
        });

        // 6. Import Audit Log (Invaluable for governance)
        Schema::create('gov_classification_import_history', function (Blueprint $table) {
            $table->id();
            $table->string('scheme', 50);
            $table->string('version', 50);
            $table->string('filename', 255);
            $table->unsignedInteger('rows_processed')->default(0);
            $table->unsignedInteger('warnings')->default(0);
            $table->decimal('duration_seconds', 8, 2)->default(0);
            $table->string('checksum', 64)->nullable();
            $table->unsignedInteger('user_id')->nullable();
            $table->timestamp('imported_at')->useCurrent();

            $table->index(['scheme', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('gov_classification_import_history');
        Schema::dropIfExists('gov_classification_snipe_mappings');
        Schema::dropIfExists('gov_classification_external_mappings');
        Schema::dropIfExists('gov_classification_synonyms');
        Schema::dropIfExists('gov_classification_metadata');
        Schema::dropIfExists('gov_classification_nodes');
    }
};
