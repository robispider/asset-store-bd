<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('gov_inventory_movements', function (Blueprint $table) {
            $table->integer('balance_after')->after('quantity')->nullable();
        });
    }

    public function down()
    {
        Schema::table('gov_inventory_movements', function (Blueprint $table) {
            $table->dropColumn('balance_after');
        });
    }
};
