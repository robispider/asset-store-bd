<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_location_profiles', function (Blueprint $table) {
            $table->string('office_level', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('gov_location_profiles', function (Blueprint $table) {
            $table->dropColumn('office_level');
        });
    }
};
