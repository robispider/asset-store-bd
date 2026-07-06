<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateServiceRequestsTables extends Migration
{
    public function up()
    {
        // 1. Drop old tables if they exist from earlier development drafts
        Schema::dropIfExists('custom_item_requests');
        Schema::dropIfExists('custom_request_items');
        Schema::dropIfExists('custom_requests');
        Schema::dropIfExists('custom_service_request_events');
        Schema::dropIfExists('custom_service_request_items');
        Schema::dropIfExists('custom_service_requests');

        // 2. Parent Request Table (The Service Request Document)
        Schema::create('custom_service_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->string('request_number', 50)->unique();
            $table->integer('requested_by')->unsigned();
            $table->integer('approved_by')->unsigned()->nullable();
            
            $table->string('request_type', 30)->index(); // onboarding, replacement, etc.
            $table->string('purpose', 255);
            $table->text('justification');
            $table->date('required_by_date')->nullable();
            $table->integer('delivery_location_id')->unsigned()->nullable();
            $table->string('cost_center', 50)->nullable();
            
            $table->string('approval_status', 30)->default('draft')->index();
            $table->string('fulfillment_status', 30)->default('unstarted')->index();
            
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('requested_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');
        });

        // 3. Child Items Table (The Line Items with Separation of Quantities)
        Schema::create('custom_service_request_items', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('request_id')->unsigned();
            
            // Polymorphic structure for the original requested item
            $table->string('requested_type', 50);
            $table->integer('requested_id')->unsigned();
            
            // Polymorphic structure for the actual issued item (supports substitutions)
            $table->string('fulfilled_type', 50)->nullable();
            $table->integer('fulfilled_id')->unsigned()->nullable();
            
            $table->integer('requested_qty')->default(1);
            $table->integer('approved_qty')->default(0);
            $table->integer('reserved_qty')->default(0);
            $table->integer('issued_qty')->default(0);
            
            $table->string('line_approval_status', 30)->default('pending')->index();
            $table->string('line_fulfillment_status', 30)->default('unstarted')->index();
            
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('request_id')->references('id')->on('custom_service_requests')->onDelete('cascade');
            $table->index(['requested_type', 'requested_id']);
            $table->index(['fulfilled_type', 'fulfilled_id']);
        });

        // 4. Timeline Logs Table (The Immutable Event Sourcing Table)
        Schema::create('custom_service_request_events', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->integer('request_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->string('event_type', 50)->index();
            $table->json('details')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('request_id')->references('id')->on('custom_service_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('custom_service_request_events');
        Schema::dropIfExists('custom_service_request_items');
        Schema::dropIfExists('custom_service_requests');
    }
}