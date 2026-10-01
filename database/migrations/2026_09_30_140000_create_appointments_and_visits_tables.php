<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A clinic is a service point where patients queue (e.g. General OPD).
        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->string('location')->nullable();
            $table->text('description')->nullable();
            $table->boolean('requires_triage')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A visit (encounter) is one trip to the hospital, from check-in to discharge.
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->string('visit_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('visit_type', 20)->default('outpatient');
            $table->string('priority', 20)->default('normal');
            $table->string('status', 30)->index();
            $table->string('queue_number', 20);
            $table->string('complaint')->nullable();

            $table->timestamp('checked_in_at');
            $table->foreignId('checked_in_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('triaged_at')->nullable();
            $table->timestamp('consultation_started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('closing_note')->nullable();
            $table->timestamps();

            $table->index(['clinic_id', 'checked_in_at']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('clinic_id')->constrained();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('scheduled_at')->index();
            $table->string('type', 20)->default('new');
            $table->string('status', 20)->default('scheduled')->index();
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('clinics');
    }
};
