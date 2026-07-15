<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gov_ministries_directory', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary(); // Matches CSV numeric ID
            $table->string('bn_name', 255);
            $table->string('en_name', 255);
            $table->string('org_type', 100);
            $table->unsignedInteger('parent_id')->nullable();
            $table->string('hid', 255)->index(); // Structural tree path
            $table->string('domain', 255)->nullable();
            
            // Link to flat, core Snipe-IT company catalog
            $table->unsignedInteger('company_id')->nullable();
            $table->timestamps();

            $table->foreign('parent_id')->references('id')->on('gov_ministries_directory')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_ministries_directory');
    }
};
