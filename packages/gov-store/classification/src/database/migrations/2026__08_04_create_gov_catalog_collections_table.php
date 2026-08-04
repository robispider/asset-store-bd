<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gov_catalog_collections', function (Blueprint $table) {
            $table->id();
            $table->string('name', 255);
            $table->text('description')->nullable();
            $table->string('icon', 50)->default('fas fa-box'); // e.g., 'fas fa-hospital'
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('gov_catalog_collection_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('collection_id');
            $table->string('code', 50); // Maps to UNSPSC gov_catalog_nodes.code
            $table->timestamps();

            $table->foreign('collection_id')->references('id')->on('gov_catalog_collections')->onDelete('cascade');
            $table->foreign('code')->references('code')->on('gov_catalog_nodes')->onDelete('cascade');
            
            $table->unique(['collection_id', 'code']); // Prevent duplicates
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_catalog_collection_nodes');
        Schema::dropIfExists('gov_catalog_collections');
    }
};