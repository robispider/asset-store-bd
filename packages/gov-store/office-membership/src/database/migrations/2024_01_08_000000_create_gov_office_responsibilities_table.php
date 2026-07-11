<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGovOfficeResponsibilitiesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('gov_office_responsibilities', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('location_id')->unsigned();
            $table->integer('user_id')->unsigned();
            $table->string('role_slug', 50)->index();
            $table->timestamps();

            // Foreign keys pointing to core Snipe-IT tables
            $table->foreign('location_id')->references('id')->on('locations')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Unique index prevents duplicate role designations for the same user in the same office context
            $table->unique(['location_id', 'user_id', 'role_slug'], 'gov_office_resp_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('gov_office_responsibilities');
    }
}