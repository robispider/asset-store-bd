<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 0. Drop old Phase 1 & 2 structural tables safely
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('gov_profile_history');
        Schema::dropIfExists('gov_profile_capabilities');
        Schema::dropIfExists('gov_profiles');
        Schema::enableForeignKeyConstraints();

        // 1. The Policy Artifacts (Agnostic of targets)
        Schema::create('gov_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('scope')->default('GLOBAL'); // GLOBAL, COMPANY, LOCATION
            $table->string('owner_type')->nullable(); // Polymorphic owner (e.g. App\Models\Location)
            $table->unsignedInteger('owner_id')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, PUBLISHED, ARCHIVED
            $table->string('version')->default('1.0.0');
            $table->timestamps();
        });

        // 2. The Policy Rules (Capabilities assigned to the Profile)
        Schema::create('gov_profile_capabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('capability_code'); 
            $table->json('config_payload')->nullable();

            $table->foreign('profile_id')->references('id')->on('gov_profiles')->onDelete('cascade');
        });

        // 3. The Adoption / Assignment Matrix (Who is using the policy)
        Schema::create('gov_profile_assignments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('target_type'); // e.g. App\Models\Category, App\Models\AssetModel
            $table->unsignedInteger('target_id');
            $table->unsignedInteger('assigned_by')->nullable(); // Admin User ID
            $table->timestamp('effective_from')->useCurrent();
            $table->timestamp('effective_to')->nullable();
            $table->timestamps();

            $table->foreign('profile_id')->references('id')->on('gov_profiles')->onDelete('cascade');
            // Ensure a target only has one active profile assignment at a time
            $table->unique(['target_type', 'target_id', 'effective_to'], 'gov_active_profile_unique');
        });

        $this->seedPolicyCatalog();
    }

    protected function seedPolicyCatalog()
    {
        $now = now();

        // A. Create Global Standard Policies
        $assetPolicyId = DB::table('gov_profiles')->insertGetId([
            'name' => 'System Default Asset Standard', 'scope' => 'GLOBAL', 
            'status' => 'PUBLISHED', 'version' => '1.0.0', 'created_at' => $now, 'updated_at' => $now
        ]);

        $consumablePolicyId = DB::table('gov_profiles')->insertGetId([
            'name' => 'System Default Consumable Standard', 'scope' => 'GLOBAL', 
            'status' => 'PUBLISHED', 'version' => '1.0.0', 'created_at' => $now, 'updated_at' => $now
        ]);

        // B. Assign Capabilities to the Policies
        // Asset Standard: requires qty, serials, and creates assets natively
        DB::table('gov_profile_capabilities')->insert([
            ['profile_id' => $assetPolicyId, 'capability_code' => 'require_quantity'],
            ['profile_id' => $assetPolicyId, 'capability_code' => 'require_serial'],
            ['profile_id' => $assetPolicyId, 'capability_code' => 'create_assets']
        ]);

        // Consumable Standard: requires qty and posts to Kardex
        DB::table('gov_profile_capabilities')->insert([
            ['profile_id' => $consumablePolicyId, 'capability_code' => 'require_quantity'],
            ['profile_id' => $consumablePolicyId, 'capability_code' => 'post_inventory']
        ]);

        // C. Adopt Policies for Existing Snipe-IT Categories
        $categories = DB::table('categories')->get();
        $assignments = [];

        foreach ($categories as $category) {
            // Determine which policy to adopt based on Snipe-IT's native category type
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