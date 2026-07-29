<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_tracking_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('display_name');
            $table->string('icon')->default('fa fa-tag');
            $table->string('color', 7)->default('#3c8dbc'); // Hex color code
            $table->string('validation_policy')->default('WARN'); // INFORM_ONLY, WARN, REQUIRE_OVERRIDE, BLOCK
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_tracking_types');
    }
};
