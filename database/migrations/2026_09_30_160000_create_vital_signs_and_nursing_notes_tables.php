<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vital_signs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('recorded_at')->index();

            // Stored in SI units: °C, mmHg, /min, %, kg, cm, mmol/L.
            $table->decimal('temperature', 4, 1)->nullable();
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('respiratory_rate')->nullable();
            $table->unsignedTinyInteger('spo2')->nullable();
            $table->boolean('on_oxygen')->default(false);
            $table->char('consciousness', 1)->nullable(); // AVPU (+ C = new confusion)
            $table->decimal('weight', 5, 2)->nullable();
            $table->decimal('height', 5, 1)->nullable();
            $table->decimal('bmi', 4, 1)->nullable();
            $table->unsignedTinyInteger('pain_score')->nullable();
            $table->decimal('blood_glucose', 4, 1)->nullable();
            $table->unsignedTinyInteger('news2_score')->nullable();
            $table->text('notes')->nullable();

            // Clinical records are never deleted, only marked as entered in error.
            $table->timestamp('voided_at')->nullable();
            $table->foreignId('voided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('void_reason')->nullable();
            $table->timestamps();

            $table->index(['patient_id', 'recorded_at']);
        });

        Schema::create('nursing_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20)->default('general');
            $table->text('note');
            $table->timestamps();

            $table->index(['patient_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nursing_notes');
        Schema::dropIfExists('vital_signs');
    }
};
