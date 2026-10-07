<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_experiment_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('label');
            $table->string('profile');
            $table->unsignedInteger('initiated_by');
            $table->unsignedInteger('seed')->default(2026);
            $table->string('status')->default('pending')->index();
            $table->string('phase')->nullable();
            $table->json('report')->nullable();
            $table->text('error')->nullable();
            $table->text('password');
            $table->timestamp('anchor_date');
            $table->timestamp('wiped_at')->nullable();
            $table->timestamps();
        });
        Schema::create('gov_experiment_records', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('run_id');
            $table->string('table_name', 100);
            $table->string('record_key', 100);
            $table->string('logical_key')->nullable();
            $table->timestamps();
            $table->foreign('run_id')->references('id')->on('gov_experiment_runs')->onDelete('cascade');
            $table->unique(['run_id', 'table_name', 'record_key'], 'gov_experiment_record_unique');
        });
        Schema::create('gov_experiment_actions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('run_id')->nullable();
            $table->unsignedInteger('actor_id');
            $table->string('action');
            $table->string('scope')->default('dataset');
            $table->json('details')->nullable();
            $table->timestamps();
        });
        Schema::create('gov_experiment_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('gov_experiment_failed_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_experiment_failed_jobs');
        Schema::dropIfExists('gov_experiment_jobs');
        Schema::dropIfExists('gov_experiment_records');
        Schema::dropIfExists('gov_experiment_actions');
        Schema::dropIfExists('gov_experiment_runs');
    }
};
