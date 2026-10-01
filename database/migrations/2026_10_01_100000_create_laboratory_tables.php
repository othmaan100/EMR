<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Result fields of a test (FBC → Hb, WBC, Platelets …).
        Schema::create('lab_test_parameters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_test_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('unit', 30)->nullable();
            $table->string('type', 10)->default('numeric'); // numeric | option | text
            $table->json('options')->nullable();            // for "option" type
            $table->decimal('ref_low', 10, 3)->nullable();
            $table->decimal('ref_high', 10, 3)->nullable();
            $table->string('ref_text', 50)->nullable();     // expected answer, e.g. "Negative"
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->timestamp('collected_at')->nullable()->after('status');
            $table->foreignId('collected_by')->nullable()->after('collected_at')->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable()->after('collected_by');
            $table->timestamp('completed_at')->nullable()->after('rejection_reason');
        });

        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->text('comment')->nullable()->after('status');
            $table->foreignId('entered_by')->nullable()->after('comment')->constrained('users')->nullOnDelete();
            $table->timestamp('entered_at')->nullable()->after('entered_by');
            $table->foreignId('verified_by')->nullable()->after('entered_at')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by');
        });

        Schema::create('lab_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_parameter_id')->nullable()->constrained()->nullOnDelete();
            // Snapshot of the parameter at result time (catalogue edits must not change old reports).
            $table->string('name', 100);
            $table->string('unit', 30)->nullable();
            $table->string('reference', 60)->nullable();
            $table->text('value');
            $table->decimal('numeric_value', 12, 3)->nullable();
            $table->string('flag', 10)->nullable(); // low | high | abnormal
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_results');

        Schema::table('lab_order_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('entered_by');
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn(['comment', 'entered_at', 'verified_at']);
        });

        Schema::table('lab_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('collected_by');
            $table->dropColumn(['collected_at', 'rejection_reason', 'completed_at']);
        });

        Schema::dropIfExists('lab_test_parameters');
    }
};
