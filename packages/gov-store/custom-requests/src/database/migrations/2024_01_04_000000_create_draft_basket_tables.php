<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Drop child and parent tables if they exist as partial failures to ensure a clean state
        Schema::dropIfExists('draft_basket_items');
        Schema::dropIfExists('draft_baskets');

        // 2. Parent table (Uses standard 32-bit increments to match rest of your schema)
        Schema::create('draft_baskets', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id');
            $table->string('status')->default('draft');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
        });

        // 3. Child table (Uses standard 32-bit unsignedInteger to match parent ID size)
        Schema::create('draft_basket_items', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('basket_id');
            $table->string('requested_type', 50);
            $table->unsignedInteger('requested_id');
            $table->unsignedInteger('requested_qty')->default(1);
            $table->timestamps();

            $table->foreign('basket_id')->references('id')->on('draft_baskets')->onDelete('cascade');
            $table->index(['requested_type', 'requested_id']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('draft_basket_items');
        Schema::dropIfExists('draft_baskets');
    }
};