<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gov_tracking_codes', function (Blueprint $table) {
            $table->timestamp('activated_at')->nullable();
            $table->boolean('activation_is_approximate')->default(false);
        });
        DB::table('gov_tracking_codes')->where('status', 'ACTIVE')->whereNotNull('updated_at')
            ->update(['activated_at' => DB::raw('updated_at'), 'activation_is_approximate' => true]);
    }

    public function down(): void
    {
        Schema::table('gov_tracking_codes', fn (Blueprint $table) => $table->dropColumn(['activated_at', 'activation_is_approximate']));
    }
};
