<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Every message to/from an outside system (summaries only; no personal data for NIN).
        Schema::create('integration_messages', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 20)->index();   // lab | pacs | payment | claims | nin
            $table->string('direction', 3);           // in | out
            $table->string('status', 10)->index();    // ok | partial | error
            $table->string('reference', 100)->nullable()->index();
            $table->string('summary', 500);
            $table->text('payload')->nullable();      // truncated, secrets removed
            $table->nullableMorphs('subject');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable()->index();
        });

        // Laboratory analysers (or their middleware) that post results.
        Schema::create('lab_analyzers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('code', 20)->unique();
            $table->string('token_hash', 64)->unique();  // sha256 of the API token
            $table->string('token_hint', 8);             // last characters, to recognise it
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // system account shown as "entered by"
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lab_analyzer_mappings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_analyzer_id')->constrained()->cascadeOnDelete();
            $table->string('analyzer_code', 50);         // the analyser's test/assay code (OBX-3)
            $table->foreignId('lab_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_parameter_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['lab_analyzer_id', 'analyzer_code']);
        });

        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->string('study_instance_uid', 100)->nullable()->after('status');
            $table->timestamp('pacs_linked_at')->nullable()->after('study_instance_uid');
        });

        Schema::create('online_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40)->unique();    // our transaction reference (unguessable)
            $table->string('gateway', 20);                // paystack | flutterwave
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->json('bill_item_ids');
            $table->string('status', 12)->default('pending')->index(); // pending | success | failed
            $table->string('channel', 10);                // portal | link
            $table->string('checkout_url', 500)->nullable();
            $table->string('gateway_reference', 100)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::table('claim_batches', function (Blueprint $table) {
            $table->string('submission_status', 20)->nullable()->after('status'); // sent | accepted | rejected | error
            $table->string('submission_reference', 100)->nullable()->after('submission_status');
            $table->timestamp('submitted_electronically_at')->nullable()->after('submission_reference');
        });

        Schema::table('patients', function (Blueprint $table) {
            $table->timestamp('nin_verified_at')->nullable()->after('national_id');
            $table->string('nin_verification_ref', 100)->nullable()->after('nin_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('patients', fn (Blueprint $t) => $t->dropColumn(['nin_verified_at', 'nin_verification_ref']));
        Schema::table('claim_batches', fn (Blueprint $t) => $t->dropColumn(['submission_status', 'submission_reference', 'submitted_electronically_at']));
        Schema::dropIfExists('online_payments');
        Schema::table('imaging_orders', fn (Blueprint $t) => $t->dropColumn(['study_instance_uid', 'pacs_linked_at']));
        Schema::dropIfExists('lab_analyzer_mappings');
        Schema::dropIfExists('lab_analyzers');
        Schema::dropIfExists('integration_messages');
    }
};
