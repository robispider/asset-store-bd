<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gov_asset_registrations', function (Blueprint $table) {
            $table->id();
            $table->uuid('intake_item_id'); // FK to our generic gov_document_items table
            $table->unsignedInteger('asset_id'); // FK to Snipe-IT's core assets.id
            $table->string('asset_tag');
            $table->string('serial_number');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('intake_item_id')->references('id')->on('gov_document_items')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_asset_registrations');
    }
};