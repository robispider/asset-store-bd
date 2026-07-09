<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class CreateGovTenantScopeTables extends Migration
{
    public function up()
    {
        Schema::dropIfExists('gov_tenant_scope_mappings');
        Schema::dropIfExists('gov_tenant_scopes');

        // 1. Create Tenant Scopes Configurations Table (Configurable Strategy per Reference Type)
        Schema::create('gov_tenant_scopes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('reference_type', 50)->unique()->index(); // categories, models, suppliers, etc.
            $table->string('scope_strategy', 30)->default('global'); // 'global', 'company', or 'office'
            $table->boolean('show_only_used')->default(false);       // True to filter active inventory only
            $table->timestamps();
        });

        // 2. Create Polymorphic References Mapping Table
        Schema::create('gov_tenant_scope_mappings', function (Blueprint $table) {
            $table->increments('id');
            
            // Scope target (Determines WHO owns/can use this reference row)
            $table->string('scope_type', 30)->index(); // 'company' or 'location'
            $table->integer('scope_id')->unsigned();    // pointing to Snipe-IT companies.id or locations.id
            
            // Reference target (Determines WHAT specific reference row is locked)
            $table->string('reference_type', 50);      // 'category', 'model', 'supplier', 'manufacturer', 'fieldset'
            $table->integer('reference_id')->unsigned(); // pointing to the respective reference table key
            
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
            $table->index(['reference_type', 'reference_id']);
        });

        // 3. Seed Standard Enterprise System Defaults (Ensures instant out-of-the-box readiness)
        $this->seedSystemDefaultPolicies();
    }

    public function down()
    {
        Schema::dropIfExists('gov_tenant_scope_mappings');
        Schema::dropIfExists('gov_tenant_scopes');
    }

    private function seedSystemDefaultPolicies()
    {
        $defaults = [
            ['reference_type' => 'categories',     'scope_strategy' => 'global',  'show_only_used' => false],
            ['reference_type' => 'models',         'scope_strategy' => 'global',  'show_only_used' => false],
            ['reference_type' => 'manufacturers',  'scope_strategy' => 'global',  'show_only_used' => false],
            ['reference_type' => 'suppliers',      'scope_strategy' => 'office',  'show_only_used' => false], // Decentralized Office Procurement
            ['reference_type' => 'locations',      'scope_strategy' => 'company', 'show_only_used' => false], // Strict Ministry Building Trees
        ];

        foreach ($defaults as $row) {
            $row['created_at'] = now();
            $row['updated_at'] = now();
            DB::table('gov_tenant_scopes')->insert($row);
        }
    }
}