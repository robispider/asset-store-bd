<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // 0. Drop old tables safely if they exist from previous iterations
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('gov_requirement_definitions');
        Schema::dropIfExists('gov_capabilities');
        Schema::dropIfExists('gov_profile_capabilities');
        Schema::dropIfExists('gov_profiles');
        Schema::enableForeignKeyConstraints();

        // 1. The 4-Layer Hierarchy (Global -> Major Type -> Category -> Model)
        Schema::create('gov_profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->string('name');
            $table->string('layer'); // GLOBAL, MAJOR_TYPE, CATEGORY, MODEL
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('gov_profiles')->onDelete('cascade');
        });

        // 2. The Plugin Composer (Links a profile to CapabilityRegistry keys)
        Schema::create('gov_profile_capabilities', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('profile_id');
            $table->string('capability_code'); // Matches CapabilityRegistry keys
            $table->json('config_payload')->nullable();

            $table->foreign('profile_id')->references('id')->on('gov_profiles')->onDelete('cascade');
        });

        $this->seedBaselineProfiles();
    }

    protected function seedBaselineProfiles()
    {
        // A. Create the Tiers
        $globalId = DB::table('gov_profiles')->insertGetId(['name' => 'Government Default', 'layer' => 'GLOBAL', 'parent_id' => null, 'created_at' => now(), 'updated_at' => now()]);
        $assetId  = DB::table('gov_profiles')->insertGetId(['name' => 'Hardware Asset', 'layer' => 'MAJOR_TYPE', 'parent_id' => $globalId, 'created_at' => now(), 'updated_at' => now()]);
        $consumableId = DB::table('gov_profiles')->insertGetId(['name' => 'Consumable Supply', 'layer' => 'MAJOR_TYPE', 'parent_id' => $globalId, 'created_at' => now(), 'updated_at' => now()]);
        $notebookId = DB::table('gov_profiles')->insertGetId(['name' => 'Notebook', 'layer' => 'CATEGORY', 'parent_id' => $assetId, 'created_at' => now(), 'updated_at' => now()]);

        // B. Assign Plugins (Capabilities) via String Codes
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $globalId, 'capability_code' => 'require_quantity']);
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $consumableId, 'capability_code' => 'post_inventory']);
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $assetId, 'capability_code' => 'create_assets']);
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $notebookId, 'capability_code' => 'require_serial']);
        DB::table('gov_profile_capabilities')->insert(['profile_id' => $notebookId, 'capability_code' => 'require_warranty']);
    }

    public function down()
    {
        Schema::dropIfExists('gov_profile_capabilities');
        Schema::dropIfExists('gov_profiles');
    }
};