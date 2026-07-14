<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Only create if the parent table does not exist
        if (!Schema::hasTable('draft_baskets')) {
            Schema::create('draft_baskets', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('user_id');
                $table->string('status')->default('draft');
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->index(['user_id', 'status']);
            });
        }

        // Only create if the child table does not exist
        if (!Schema::hasTable('draft_basket_items')) {
            Schema::create('draft_basket_items', function (Blueprint $table) {
                $table->id();
                $table->unsignedInteger('basket_id');
                $table->string('requested_type', 50);
                $table->unsignedInteger('requested_id');
                $table->unsignedInteger('requested_qty')->default(1);
                $table->timestamps();

                $table->foreign('basket_id')->references('id')->on('draft_baskets')->onDelete('cascade');
                $table->index(['requested_type', 'requested_id']);
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('draft_basket_items');
        Schema::dropIfExists('draft_baskets');
    }
};
