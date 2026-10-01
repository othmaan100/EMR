<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Miscellaneous billable services (registration, consultation fees, procedures…).
        Schema::create('services', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('category', 50);
            $table->foreignId('clinic_id')->nullable()->constrained()->nullOnDelete(); // consultation fee for this clinic
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Price of any billable item; insurance_provider_id NULL = default (self-pay) price.
        Schema::create('prices', function (Blueprint $table) {
            $table->id();
            $table->morphs('billable');
            $table->foreignId('insurance_provider_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->timestamps();
            $table->unique(['billable_type', 'billable_id', 'insurance_provider_id'], 'prices_unique');
        });

        Schema::table('insurance_providers', function (Blueprint $table) {
            $table->unsignedTinyInteger('coverage_percent')->default(100)->after('type');
        });

        // One bill (invoice) per visit; registration charges go on a visit-less bill.
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->string('bill_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->foreignId('insurance_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('claim_status', 20)->default('none')->index(); // none | pending | submitted | paid | rejected
            $table->timestamp('claim_submitted_at')->nullable();
            $table->timestamp('claim_paid_at')->nullable();
            $table->string('claim_reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('bill_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bill_id')->constrained()->cascadeOnDelete();
            $table->nullableMorphs('billable'); // Service, LabTest, ImagingTest, Drug
            $table->nullableMorphs('source');   // what triggered it: Visit, LabOrderItem, ImagingOrder, PrescriptionItem…
            $table->string('description');
            $table->decimal('quantity', 10, 2)->default(1);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->decimal('insurance_amount', 12, 2)->default(0);
            $table->decimal('patient_amount', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('discount_reason')->nullable();
            $table->foreignId('discounted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('receipt_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('method', 20);
            $table->string('reference', 100)->nullable();
            $table->foreignId('received_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();
            $table->index('created_at');
        });

        // Which items a payment paid for (so a reversal can be undone exactly).
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bill_item_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
        });
    }

    public function down(): void
    {
        foreach (['payment_allocations', 'payments', 'bill_items', 'bills', 'prices', 'services'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('insurance_providers', fn (Blueprint $t) => $t->dropColumn('coverage_percent'));
    }
};
