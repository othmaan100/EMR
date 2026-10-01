<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('insurance_providers', function (Blueprint $table) {
            // Payer insists on a pre-authorisation (PA) code before a bill can be claimed.
            $table->boolean('requires_authorization')->default(false)->after('coverage_percent');
        });

        Schema::create('claim_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_number', 30)->unique();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->date('period_from');
            $table->date('period_to');
            $table->string('status', 20)->default('draft')->index(); // draft | submitted | reconciled
            $table->decimal('amount_claimed', 14, 2)->default(0);
            $table->decimal('amount_paid', 14, 2)->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->date('paid_on')->nullable();
            $table->string('payment_reference', 100)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('claim_batch_id')->nullable()->after('claim_status')->constrained()->nullOnDelete();
            $table->string('authorization_code', 50)->nullable()->after('claim_batch_id');
            $table->decimal('claim_amount', 12, 2)->nullable()->after('authorization_code');      // frozen when batched
            $table->decimal('claim_amount_paid', 12, 2)->default(0)->after('claim_amount');
            $table->string('claim_rejection_reason')->nullable()->after('claim_amount_paid');
            $table->timestamp('claim_transferred_at')->nullable()->after('claim_rejection_reason'); // shortfall moved to patient
        });

        Schema::create('preauthorizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('insurance_provider_id')->constrained()->restrictOnDelete();
            $table->foreignId('bill_id')->nullable()->constrained()->nullOnDelete();
            $table->text('services');
            $table->string('diagnosis')->nullable();
            $table->decimal('amount_requested', 12, 2)->nullable();
            $table->string('status', 20)->default('requested')->index(); // requested | approved | declined
            $table->string('code', 50)->nullable()->index();
            $table->decimal('amount_approved', 12, 2)->nullable();
            $table->date('valid_until')->nullable();
            $table->string('notes', 500)->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('preauthorizations');
        Schema::table('bills', function (Blueprint $table) {
            $table->dropConstrainedForeignId('claim_batch_id');
            $table->dropColumn(['authorization_code', 'claim_amount', 'claim_amount_paid', 'claim_rejection_reason', 'claim_transferred_at']);
        });
        Schema::dropIfExists('claim_batches');
        Schema::table('insurance_providers', fn (Blueprint $t) => $t->dropColumn('requires_authorization'));
    }
};
