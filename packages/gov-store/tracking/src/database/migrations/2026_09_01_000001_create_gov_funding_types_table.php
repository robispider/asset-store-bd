<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('gov_funding_types');

        Schema::create('gov_funding_types', function (Blueprint $table) {
            $table->id();
            $table->string('primary_type'); // ADP, REVENUE, OTHER
            $table->string('name'); // e.g., "GoB (Taka)", "Project Aid (PA)"
            $table->string('description')->nullable();
            $table->timestamps();
        });

        $now = now();
        DB::table('gov_funding_types')->insert([
            ['primary_type' => 'REVENUE', 'name' => 'GoB (Taka)', 'description' => 'Standard non-development domestic tax/non-tax resources.', 'created_at' => $now, 'updated_at' => $now],
            ['primary_type' => 'REVENUE', 'name' => 'Self-Financed / Capital', 'description' => 'Autonomous organization internal revenue reserves.', 'created_at' => $now, 'updated_at' => $now],
            ['primary_type' => 'ADP', 'name' => 'GoB (Taka)', 'description' => 'Domestic resources allocated for development spending.', 'created_at' => $now, 'updated_at' => $now],
            ['primary_type' => 'ADP', 'name' => 'Project Aid (PA Loan)', 'description' => 'Gross foreign loan disbursements managed by ERD.', 'created_at' => $now, 'updated_at' => $now],
            ['primary_type' => 'ADP', 'name' => 'Foreign Grant', 'description' => 'External non-repayable development grants.', 'created_at' => $now, 'updated_at' => $now],
            ['primary_type' => 'ADP', 'name' => 'Donation / NGO Grant', 'description' => 'Co-financed philanthropic development balances.', 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gov_funding_types');
    }
};