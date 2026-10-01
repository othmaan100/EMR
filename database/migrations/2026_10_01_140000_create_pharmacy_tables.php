<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drugs', function (Blueprint $table) {
            $table->string('dispensing_unit', 30)->nullable()->after('route'); // tablet, bottle, vial…
            $table->unsignedInteger('reorder_level')->default(0)->after('dispensing_unit');
        });

        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('stock_receipts', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('invoice_number', 50)->nullable();
            $table->date('received_on');
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained();
            $table->foreignId('stock_receipt_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 50);
            $table->date('expiry_date')->index();
            $table->unsignedInteger('quantity_received');
            $table->unsignedInteger('quantity_on_hand');
            $table->decimal('unit_cost', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['drug_id', 'quantity_on_hand', 'expiry_date']);
        });

        // Ledger: every change to stock, positive or negative.
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('drug_id')->constrained();
            $table->foreignId('stock_batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // receipt | dispense | adjustment | disposal
            $table->integer('quantity');
            $table->nullableMorphs('reference');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::table('prescriptions', function (Blueprint $table) {
            $table->timestamp('dispensed_at')->nullable()->after('status');
            $table->foreignId('dispensed_by')->nullable()->after('dispensed_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity_dispensed')->default(0)->after('quantity');
            $table->string('not_dispensed_reason')->nullable()->after('quantity_dispensed');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', fn (Blueprint $t) => $t->dropColumn(['quantity_dispensed', 'not_dispensed_reason']));
        Schema::table('prescriptions', function (Blueprint $t) {
            $t->dropConstrainedForeignId('dispensed_by');
            $t->dropColumn('dispensed_at');
        });
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_batches');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('suppliers');
        Schema::table('drugs', fn (Blueprint $t) => $t->dropColumn(['dispensing_unit', 'reorder_level']));
    }
};
