<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('theatres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('surgical_procedures', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name')->unique();
            $table->string('specialty', 50);
            $table->unsignedSmallInteger('typical_minutes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('surgeries', function (Blueprint $table) {
            $table->id();
            $table->string('surgery_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('pregnancy_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('surgical_procedure_id')->nullable()->constrained()->nullOnDelete();
            $table->string('procedure_name');
            $table->foreignId('theatre_id')->constrained();
            $table->foreignId('surgeon_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assistant')->nullable();
            $table->foreignId('anaesthetist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('urgency', 20)->default('elective');
            $table->dateTime('scheduled_at')->index();
            $table->unsignedSmallInteger('estimated_minutes')->default(60);
            $table->text('indication');
            $table->string('status', 20)->default('scheduled')->index();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();

            // Pre-operative assessment
            $table->boolean('consent_signed')->default(false);
            $table->boolean('fasting_confirmed')->default(false);
            $table->boolean('site_marked')->default(false);
            $table->unsignedTinyInteger('asa_grade')->nullable();
            $table->unsignedTinyInteger('blood_units_available')->nullable();
            $table->text('preop_notes')->nullable();
            $table->foreignId('assessed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assessed_at')->nullable();

            // WHO checklist: {sign_in|time_out|sign_out: {items: [...], by, by_name, at}}
            $table->json('checklist')->nullable();

            // Anaesthesia
            $table->string('anaesthesia_type', 30)->nullable();
            $table->string('airway', 50)->nullable();
            $table->text('anaesthesia_drugs')->nullable();
            $table->text('fluids')->nullable();
            $table->text('anaesthesia_notes')->nullable();

            // Timings
            $table->timestamp('in_theatre_at')->nullable();
            $table->timestamp('incision_at')->nullable();
            $table->timestamp('out_at')->nullable();

            // Operation note
            $table->text('findings')->nullable();
            $table->text('procedure_performed')->nullable();
            $table->unsignedSmallInteger('blood_loss_ml')->nullable();
            $table->string('specimens')->nullable();
            $table->string('implants')->nullable();
            $table->string('drains')->nullable();
            $table->string('closure')->nullable();
            $table->text('complications')->nullable();
            $table->text('postop_orders')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('surgery_observations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surgery_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->unsignedTinyInteger('spo2')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['surgery_observations', 'surgeries', 'surgical_procedures', 'theatres'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
