<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Core Capability Index
        Schema::create('gov_capabilities', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('type'); // VALIDATION, EXECUTION
        });

        // 2. Recursive Onboarding / Product Profiles
        Schema::create('gov_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('gov_profiles')->onDelete('cascade');
        });

        // 3. Profile-Capability Lego Composition
        Schema::create('gov_profile_capabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('capability_id');
            $table->json('config_payload')->nullable();

            $table->foreign('profile_id')->references('id')->on('gov_profiles')->onDelete('cascade');
            $table->foreign('capability_id')->references('id')->on('gov_capabilities')->onDelete('cascade');
        });

        // 4. Requirement Definitions (linked directly to capabilities)
        Schema::create('gov_requirement_definitions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('capability_id');
            $table->string('field_key');
            $table->string('field_type');
            $table->string('validation_rules')->nullable();

            $table->foreign('capability_id')->references('id')->on('gov_capabilities')->onDelete('cascade');
        });

        // 5. Generic Document Header
        Schema::create('gov_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('document_number')->unique();
            $table->string('type'); // e.g., 'receipt', 'issue'
            $table->string('status')->default('DRAFT');
            $table->json('compiled_profile_snapshot')->nullable();

            // Tenant Scope tracking
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('created_by');
            $table->timestamps();
        });

        // 6. Generic Document Item Rows (The Grid Lines)
        Schema::create('gov_document_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('document_id');
            $table->string('product_type'); // Polymorphic (e.g., 'consumable', 'accessory', 'asset_model')
            $table->unsignedInteger('product_id');
            $table->integer('quantity');
            $table->decimal('unit_cost', 15, 2)->nullable();

            $table->foreign('document_id')->references('id')->on('gov_documents')->onDelete('cascade');
        });

        // 7. Generic Document Item Metadata (Unified EAV storage)
        Schema::create('gov_document_item_meta', function (Blueprint $table) {
            $table->id();
            $table->uuid('document_item_id');
            $table->string('field_key');
            $table->text('value');
            $table->integer('row_index')->default(0);

            $table->foreign('document_item_id')->references('id')->on('gov_document_items')->onDelete('cascade');
        });

        // --- SEED SYSTEM METADATA IMMEDIATELY ---
        $this->seedMetaArchitecture();
    }

    protected function seedMetaArchitecture()
    {
        // A. Seed Capabilities
        $capQuantity = DB::table('gov_capabilities')->insertGetId(['code' => 'require_quantity', 'type' => 'VALIDATION']);
        $capSerial   = DB::table('gov_capabilities')->insertGetId(['code' => 'require_serial', 'type' => 'VALIDATION']);
        $capWarranty = DB::table('gov_capabilities')->insertGetId(['code' => 'require_warranty', 'type' => 'VALIDATION']);
        $capLedger   = DB::table('gov_capabilities')->insertGetId(['code' => 'post_inventory', 'type' => 'EXECUTION']);
        $capAsset    = DB::table('gov_capabilities')->insertGetId(['code' => 'create_assets', 'type' => 'EXECUTION']);

        // B. Seed Requirement Definitions
        DB::table('gov_requirement_definitions')->insert([
            'capability_id' => $capSerial,
            'field_key' => 'serial_number',
            'field_type' => 'string',
            'validation_rules' => 'required'
        ]);

        DB::table('gov_requirement_definitions')->insert([
            'capability_id' => $capWarranty,
            'field_key' => 'warranty_months',
            'field_type' => 'integer',
            'validation_rules' => 'required|integer|min:0'
        ]);

        // C. Seed CSS Inheritance Profiles
        $profileGlobal   = DB::table('gov_profiles')->insertGetId(['parent_id' => null, 'name' => 'Global Base', 'created_at' => now(), 'updated_at' => now()]);
        $profileAsset    = DB::table('gov_profiles')->insertGetId(['parent_id' => $profileGlobal, 'name' => 'Asset Major Type', 'created_at' => now(), 'updated_at' => now()]);
        $profileNotebook = DB::table('gov_profiles')->insertGetId(['parent_id' => $profileAsset, 'name' => 'Notebook Category', 'created_at' => now(), 'updated_at' => now()]);

        // D. Compose Capabilities (Lego Assembly)
        // 1. Global Base gets 'require_quantity'
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $profileGlobal, 'capability_id' => $capQuantity]);
        
        // 2. Asset Major Type gets 'create_assets'
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $profileAsset, 'capability_id' => $capAsset]);
        
        // 3. Notebook Category gets 'require_serial' and 'require_warranty'
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $profileNotebook, 'capability_id' => $capSerial]);
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $profileNotebook, 'capability_id' => $capWarranty]);
    }

    public function down()
    {
        Schema::dropIfExists('gov_document_item_meta');
        Schema::dropIfExists('gov_document_items');
        Schema::dropIfExists('gov_documents');
        Schema::dropIfExists('gov_requirement_definitions');
        Schema::dropIfExists('gov_profile_capabilities');
        Schema::dropIfExists('gov_profiles');
        Schema::dropIfExists('gov_capabilities');
    }
};