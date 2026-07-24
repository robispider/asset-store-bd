<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 1. Drop old category-bound profile tables safely
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('gov_profile_capabilities');
        Schema::dropIfExists('gov_profiles');
        Schema::enableForeignKeyConstraints();

        // 2. The Policy Catalog (GPO Goggles: Completely agnostic of categories)
        Schema::create('gov_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // e.g. "National IT Laptop Standard"
            $table->string('scope')->default('GLOBAL'); // GLOBAL, COMPANY, LOCATION
            $table->string('owner_type')->nullable(); // Polymorphic owner (e.g. App\Models\Location)
            $table->unsignedInteger('owner_id')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, PUBLISHED, ARCHIVED
            $table->string('version')->default('1.0.0');
            $table->timestamps();
        });

        // 3. The Capability Map (Links the GPO to its plugins)
        Schema::create('gov_profile_capabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('capability_code'); // Matches CapabilityRegistry keys
            $table->json('config_payload')->nullable();

            $table->foreign('profile_id')->references('id')->on('gov_profiles')->onDelete('cascade');
        });

        // 4. The GPO Assignment Table (Who is adopting the GPO Goggles)
        Schema::create('gov_profile_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('target_type'); // Polymorphic target (e.g. App\Models\Category)
            $table->unsignedInteger('target_id');
            $table->unsignedInteger('assigned_by')->nullable(); // Admin User ID
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->foreign('profile_id')->references('id')->on('gov_profiles')->onDelete('cascade');
            $table->unique(['target_type', 'target_id', 'effective_to'], 'gov_active_profile_unique');
        });

        $this->seedPolicyCatalog();
    }

    protected function seedPolicyCatalog()
    {
        $now = now();

        // A. Seed Global Policies
        $assetPolicyId = DB::table('gov_profiles')->insertGetId([
            'name' => 'System Default Asset Standard', 'scope' => 'GLOBAL', 
            'status' => 'PUBLISHED', 'version' => '1.0.0', 'created_at' => $now, 'updated_at' => $now
        ]);

        $consumablePolicyId = DB::table('gov_profiles')->insertGetId([
            'name' => 'System Default Consumable Standard', 'scope' => 'GLOBAL', 
            'status' => 'PUBLISHED', 'version' => '1.0.0', 'created_at' => $now, 'updated_at' => $now
        ]);

        // B. Assign Capabilities to the GPOs
        // Assets require quantity, serials, and create physical assets on post
        DB::table('gov_profile_capabilities')->insert([
            ['profile_id' => $assetPolicyId, 'capability_code' => 'require_quantity', 'config_payload' => null],
            ['profile_id' => $assetPolicyId, 'capability_code' => 'require_serial', 'config_payload' => null],
            ['profile_id' => $assetPolicyId, 'capability_code' => 'create_assets', 'config_payload' => null]
        ]);

        // Consumables require quantity and post to the Kardex ledger on post
        DB::table('gov_profile_capabilities')->insert([
            ['profile_id' => $consumablePolicyId, 'capability_code' => 'require_quantity', 'config_payload' => null],
            ['profile_id' => $consumablePolicyId, 'capability_code' => 'post_inventory', 'config_payload' => null]
        ]);

        // C. Adopt Policies for Existing Snipe-IT Categories Dynamically
        $categories = DB::table('categories')->get();
        $assignments = [];

        foreach ($categories as $category) {
            // Asset categories adopt the Asset Standard; others adopt Consumable Standard
            $policyId = ($category->category_type === 'asset') ? $assetPolicyId : $consumablePolicyId;

            $assignments[] = [
                'profile_id'     => $policyId,
                'target_type'    => 'App\Models\Category',
                'target_id'      => $category->id,
                'assigned_by'    => 1, // System admin
                'effective_from' => $now,
                'created_at'     => $now,
                'updated_at'     => $now,
            ];
        }

        if (!empty($assignments)) {
            DB::table('gov_profile_assignments')->insert($assignments);
        }
    }

    public function down()
    {
        Schema::dropIfExists('gov_profile_assignments');
        Schema::dropIfExists('gov_profile_capabilities');
        Schema::dropIfExists('gov_profiles');
    }
};