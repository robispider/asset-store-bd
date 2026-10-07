<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('gov_committee_catalog_mutexes',function (Blueprint $table) { $table->string('key',40)->primary(); });
        DB::table('gov_committee_catalog_mutexes')->insert([['key'=>'types:national'],['key'=>'bindings:national']]);
    }
    public function down(): void { Schema::dropIfExists('gov_committee_catalog_mutexes'); }
};
