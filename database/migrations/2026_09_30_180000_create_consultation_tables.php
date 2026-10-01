<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ---------- Catalogues ----------
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('description');
            $table->index('description');
        });

        Schema::create('lab_tests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->string('category', 50);
            $table->string('sample_type', 50)->nullable();
            $table->unsignedSmallInteger('turnaround_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('imaging_tests', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->string('modality', 30);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('drugs', function (Blueprint $table) {
            $table->id();
            $table->string('name');            // generic name
            $table->string('strength', 50)->nullable();
            $table->string('form', 30);        // tablet, syrup, injection...
            $table->string('route', 30)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'strength', 'form']);
        });

        // ---------- Consultation ----------
        Schema::create('consultations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('presenting_complaint')->nullable();
            $table->text('history')->nullable();
            $table->text('past_history')->nullable();
            $table->text('drug_history')->nullable();
            $table->text('family_social_history')->nullable();
            $table->text('systems_review')->nullable();
            $table->text('examination')->nullable();
            $table->text('assessment')->nullable();
            $table->text('plan')->nullable();
            $table->string('status', 10)->default('draft'); // draft | signed
            $table->timestamp('signed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('consultation_addenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('note');
            $table->timestamps();
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consultation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('icd10_code', 10)->nullable()->index();
            $table->string('description');
            $table->string('certainty', 20)->default('provisional');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();
        });

        // ---------- Orders ----------
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('priority', 10)->default('routine');
            $table->text('clinical_notes')->nullable();
            $table->string('status', 20)->default('requested')->index();
            $table->timestamps();
        });

        Schema::create('lab_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lab_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lab_test_id')->constrained();
            $table->string('status', 20)->default('requested');
            $table->timestamps();
        });

        Schema::create('imaging_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ordered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('imaging_test_id')->constrained();
            $table->string('priority', 10)->default('routine');
            $table->text('clinical_notes')->nullable();
            $table->string('status', 20)->default('requested')->index();
            $table->timestamps();
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->string('prescription_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('prescribed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 20)->default('pending')->index();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drug_id')->nullable()->constrained()->nullOnDelete();
            $table->string('drug_name');       // snapshot, or free text if not in formulary
            $table->string('dose', 50);
            $table->string('route', 30);
            $table->string('frequency', 20);
            $table->string('duration', 30);
            $table->unsignedInteger('quantity')->nullable();
            $table->string('instructions')->nullable();
            $table->boolean('allergy_override')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['prescription_items', 'prescriptions', 'imaging_orders', 'lab_order_items', 'lab_orders', 'diagnoses',
            'consultation_addenda', 'consultations', 'drugs', 'imaging_tests', 'lab_tests', 'icd10_codes'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
