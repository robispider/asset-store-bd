<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // NULL national owners need a normalized key; SQL unique indexes allow multiple NULLs.
        Schema::table('gov_committee_types',function (Blueprint $t) {
            $t->unsignedInteger('owner_key')->storedAs('COALESCE(owner_company_id, 0)');
            $t->unique(['owner_key','code'],'committee_type_owner_unique');
        });
        Schema::table('gov_committee_purpose_bindings',function (Blueprint $t) {
            $t->unsignedInteger('owner_key')->storedAs('COALESCE(owner_company_id, 0)');
            $t->unique(['owner_key','purpose_code','committee_type_id'],'committee_purpose_owner_unique');
        });
    }
    public function down(): void
    {
        Schema::table('gov_committee_purpose_bindings',function (Blueprint $t) { $t->dropUnique('committee_purpose_owner_unique'); $t->dropColumn('owner_key'); });
        Schema::table('gov_committee_types',function (Blueprint $t) { $t->dropUnique('committee_type_owner_unique'); $t->dropColumn('owner_key'); });
    }
};
