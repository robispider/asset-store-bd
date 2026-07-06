<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCustomItemRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('custom_item_requests', function (Blueprint $table) {
            $table->increments('id');
            
            // Polymorphic columns (Handles Asset, Consumable, Accessory, License)
            $table->string('requestable_type');
            $table->integer('requestable_id')->unsigned();
            
            // User Tracking (Matches Snipe-IT's user ID schema)
            $table->integer('requested_by')->unsigned();
            $table->integer('approved_by')->unsigned()->nullable();
            
            // Request State
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->text('notes')->nullable();
            
            $table->timestamps();
            $table->softDeletes(); 

            // Indexes for speed and Data Integrity constraints
            $table->index(['requestable_type', 'requestable_id']);
            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('custom_item_requests');
    }
}