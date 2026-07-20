<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gov_company_admins', function (Blueprint $table) {
            $table->increments('id');
            // A user can only act as the global admin for a single company context to prevent leakage
            $table->integer('user_id')->unsigned()->unique();
            $table->integer('company_id')->unsigned()->index();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_company_admins');
    }
};