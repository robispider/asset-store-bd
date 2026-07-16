<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('gov_category_governance', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('category_id')->unique();
            $table->string('governance_type', 30)->default('global'); // 'global' or 'company'
            
            // Audit & Origin
            $table->unsignedInteger('created_by_company_id')->nullable();
            $table->unsignedInteger('created_by_user_id')->nullable();
            
            $table->timestamps();

            $table->foreign('category_id')->references('id')->on('categories')->onDelete('cascade');
            $table->foreign('created_by_company_id')->references('id')->on('companies')->onDelete('set null');
            $table->foreign('created_by_user_id')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_category_governance');
    }
};