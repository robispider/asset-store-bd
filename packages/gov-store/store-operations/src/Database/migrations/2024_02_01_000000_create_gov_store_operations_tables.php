<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // 1. Goods Receipts (Inbound)
        Schema::create('gov_goods_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receipt_no')->unique();
            $table->unsignedInteger('supplier_id')->nullable();
            $table->string('purchase_type'); // CASH, TENDER, DONATION, TRANSFER
            $table->string('reference_no')->nullable();
            $table->date('reference_date')->nullable();
            $table->string('received_by_type'); // SELF, EMPLOYEE, COMMITTEE
            $table->string('committee_ref')->nullable();
            $table->string('status')->default('DRAFT'); // DRAFT, SUBMITTED, CANCELLED
            
            // Context Tracking
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('created_by');
            
            $table->timestamps();
        });

        Schema::create('gov_goods_receipt_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('goods_receipt_id');
            $table->string('stockable_type'); // Polymorphic
            $table->unsignedInteger('stockable_id');
            $table->integer('quantity');
            $table->decimal('unit_cost', 15, 2)->nullable();
            
            $table->foreign('goods_receipt_id')->references('id')->on('gov_goods_receipts')->onDelete('cascade');
        });

        // 2. Goods Issues (Outbound)
        Schema::create('gov_goods_issues', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('issue_no')->unique();
            $table->string('issue_type'); // TO_USER, TO_DEPARTMENT, SYSTEM_FULFILLMENT
            $table->unsignedInteger('issued_to_id')->nullable();
            $table->string('reference_type')->nullable(); // Polymorphic (e.g., Custom Request)
            $table->unsignedInteger('reference_id')->nullable();
            $table->string('status')->default('DRAFT');
            
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('created_by');
            
            $table->timestamps();
        });

        Schema::create('gov_goods_issue_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('goods_issue_id');
            $table->string('stockable_type');
            $table->unsignedInteger('stockable_id');
            $table->integer('quantity');
            
            $table->foreign('goods_issue_id')->references('id')->on('gov_goods_issues')->onDelete('cascade');
        });

        // 3. Stock Adjustments (Corrections)
        Schema::create('gov_stock_adjustments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('adjustment_no')->unique();
            $table->string('adjustment_type'); // PHYSICAL_COUNT, DAMAGE, LOSS, EXPIRED, CORRECTION
            $table->text('remarks')->nullable();
            $table->string('status')->default('DRAFT');
            
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('created_by');
            
            $table->timestamps();
        });

        Schema::create('gov_stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->uuid('stock_adjustment_id');
            $table->string('stockable_type');
            $table->unsignedInteger('stockable_id');
            $table->string('direction'); // IN, OUT
            $table->integer('quantity'); // Absolute value
            
            $table->foreign('stock_adjustment_id')->references('id')->on('gov_stock_adjustments')->onDelete('cascade');
        });

        // 4. Inventory Movements (The Immutable Audit Ledger)
        Schema::create('gov_inventory_movements', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('stockable_type');
            $table->unsignedInteger('stockable_id');
            $table->string('movement_type'); // IN, OUT
            $table->integer('quantity'); // Absolute value
            
            $table->string('document_type'); // Polymorphic (Receipt, Issue, Adjustment)
            $table->uuid('document_id');
            
            $table->unsignedInteger('company_id')->nullable();
            $table->unsignedInteger('location_id')->nullable();
            $table->unsignedInteger('created_by');
            
            $table->timestamp('created_at')->useCurrent(); // Immutable, no updated_at
        });
    }

    public function down()
    {
        Schema::dropIfExists('gov_inventory_movements');
        Schema::dropIfExists('gov_stock_adjustment_items');
        Schema::dropIfExists('gov_stock_adjustments');
        Schema::dropIfExists('gov_goods_issue_items');
        Schema::dropIfExists('gov_goods_issues');
        Schema::dropIfExists('gov_goods_receipt_items');
        Schema::dropIfExists('gov_goods_receipts');
    }
};
