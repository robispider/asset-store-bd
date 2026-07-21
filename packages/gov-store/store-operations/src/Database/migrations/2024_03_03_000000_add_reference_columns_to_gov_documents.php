<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('gov_documents', function (Blueprint $table) {
            $table->string('reference_no')->nullable()->after('status');
            $table->date('reference_date')->nullable()->after('reference_no');
            $table->string('purchase_type')->nullable()->after('reference_date');
        });
    }

    public function down()
    {
        Schema::table('gov_documents', function (Blueprint $table) {
            $table->dropColumn(['reference_no', 'reference_date', 'purchase_type']);
        });
    }
};