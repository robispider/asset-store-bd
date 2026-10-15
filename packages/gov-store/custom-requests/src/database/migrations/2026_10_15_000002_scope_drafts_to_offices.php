<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Request flow redesign: a draft belongs to one person in one office, so switching office never sends
 * items to the wrong store. Additive and nullable: an existing draft without an office is claimed by the
 * office its owner opens it in next (DraftBasket::getOrCreateForUser).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('draft_baskets', function (Blueprint $table) {
            $table->unsignedInteger('office_id')->nullable()->after('user_id');
            $table->index(['user_id', 'office_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('draft_baskets', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'office_id', 'status']);
            $table->dropColumn('office_id');
        });
    }
};
