<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('gov_tenant_scope_mappings', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('scope_id')->index();
        });
    }

    public function down()
    {
        Schema::table('gov_tenant_scope_mappings', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};