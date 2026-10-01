<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // General (non-drug) stores: consumables, linen, stationery, reagents, cleaning…
        Schema::create('store_items', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->string('category', 50)->index();
            $table->string('unit', 30)->default('piece');
            $table->unsignedInteger('reorder_level')->default(0);
            $table->integer('quantity_on_hand')->default(0);
            $table->decimal('average_cost', 12, 2)->default(0); // weighted average, for valuation
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Ledger: every change to store stock.
        Schema::create('store_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_item_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->index(); // receipt | issue | return | adjustment | write_off
            $table->integer('quantity');         // signed
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->integer('balance_after');
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->nullableMorphs('reference');  // Requisition, PurchaseOrder…
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('requisitions', function (Blueprint $table) {
            $table->id();
            $table->string('requisition_number', 30)->unique();
            $table->foreignId('department_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('submitted')->index(); // submitted | partially_issued | issued | rejected | cancelled
            $table->date('needed_by')->nullable();
            $table->text('notes')->nullable();
            $table->string('closed_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('issued_at')->nullable();
            $table->timestamps();
        });

        Schema::create('requisition_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('store_item_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity_requested');
            $table->unsignedInteger('quantity_issued')->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('po_number', 30)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('status', 20)->default('draft')->index(); // draft | approved | partially_received | received | cancelled
            $table->date('order_date');
            $table->date('expected_date')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('total', 14, 2)->default(0);
            $table->string('cancel_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->morphs('item');               // StoreItem or Drug
            $table->string('description');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('quantity_received')->default(0);
            $table->timestamps();
        });

        Schema::create('supplier_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 50);
            $table->date('invoice_date');
            $table->date('due_date')->nullable()->index();
            $table->decimal('amount', 14, 2);
            $table->string('status', 10)->default('unpaid')->index(); // unpaid | paid
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'invoice_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_invoices');
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('requisition_items');
        Schema::dropIfExists('requisitions');
        Schema::dropIfExists('store_movements');
        Schema::dropIfExists('store_items');
    }
};
