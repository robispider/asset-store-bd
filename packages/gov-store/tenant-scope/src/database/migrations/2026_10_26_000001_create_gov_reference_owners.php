<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gov_reference_owners', function (Blueprint $table) {
            $table->id();
            $table->string('reference_type', 30);
            $table->unsignedInteger('reference_id');
            $table->string('owner_type', 30);
            $table->unsignedInteger('owner_id')->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->text('review_reason')->nullable();
            $table->timestamps();
            $table->unique(['reference_type', 'reference_id']);
        });
        // Reviewed 26 October: legacy `local` rows have company origin (the experiment
        // creator wrote that company explicitly). `location` has no office origin.
        // Never recover an owner from adoption, including a single adoption.
        if (Schema::hasTable('gov_category_governance')) {
            DB::table('gov_category_governance')->orderBy('id')->each(function ($row) {
                $type = match ($row->governance_type) {
                    'global', 'national' => 'national',
                    'company', 'local' => $row->created_by_company_id ? 'company' : 'unresolved',
                    default => 'unresolved',
                };
                DB::table('gov_reference_owners')->insert(['reference_type' => 'category', 'reference_id' => $row->category_id,
                    'owner_type' => $type, 'owner_id' => $type === 'company' ? $row->created_by_company_id : null,
                    'review_reason' => 'Legacy governance review; adoption is not ownership.', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('gov_category_governance')->where('id', $row->id)->update(['governance_type' => $type]);
            });
        }
    }

    public function down(): void
    {
        // Ownership review is durable; do not guess how to undo governance decisions.
        Schema::dropIfExists('gov_reference_owners');
    }
};
