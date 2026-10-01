<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('patients', function (Blueprint $table) {
            $table->foreignId('mother_id')->nullable()->after('registered_by')->constrained('patients')->nullOnDelete();
        });

        Schema::create('pregnancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active')->index(); // active | delivered | ended
            $table->date('lmp')->nullable();
            $table->date('edd');                    // from LMP (Naegele) or dating scan
            $table->boolean('edd_by_scan')->default(false);
            $table->unsignedTinyInteger('gravida');
            $table->unsignedTinyInteger('parity')->default(0);
            $table->unsignedTinyInteger('abortions')->default(0);
            $table->unsignedTinyInteger('living_children')->default(0);
            $table->json('risk_factors')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('labour_started_at')->nullable();
            $table->string('end_reason')->nullable(); // miscarriage, transfer out, …
            $table->foreignId('booked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('anc_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->decimal('weight', 5, 1)->nullable();
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->unsignedTinyInteger('fundal_height')->nullable();
            $table->string('presentation', 20)->nullable();
            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->boolean('fetal_movement')->nullable();
            $table->string('urine_protein', 10)->nullable();
            $table->string('oedema', 10)->nullable();
            $table->decimal('haemoglobin', 4, 1)->nullable();
            $table->json('interventions')->nullable();
            $table->text('complaints')->nullable();
            $table->text('notes')->nullable();
            $table->date('next_visit')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('partograph_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->timestamp('recorded_at');
            $table->decimal('cervical_dilation', 3, 1)->nullable();
            $table->unsignedTinyInteger('descent')->nullable();       // fifths palpable 5..0
            $table->unsignedTinyInteger('contractions')->nullable();  // per 10 minutes
            $table->string('contraction_strength', 10)->nullable();   // <20s | 20-40s | >40s
            $table->unsignedSmallInteger('fetal_heart_rate')->nullable();
            $table->char('liquor', 1)->nullable();                    // I C M B A
            $table->string('moulding', 4)->nullable();
            $table->unsignedSmallInteger('pulse')->nullable();
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('admission_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('delivered_at');
            $table->string('mode', 30);
            $table->unsignedTinyInteger('gestation_weeks')->nullable();
            $table->unsignedSmallInteger('blood_loss_ml')->nullable();
            $table->boolean('placenta_complete')->default(true);
            $table->string('perineum', 20)->nullable();
            $table->json('complications')->nullable();
            $table->string('maternal_outcome', 20)->default('alive');
            $table->text('notes')->nullable();
            $table->foreignId('attended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('babies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete(); // registered baby
            $table->string('sex', 10);
            $table->unsignedSmallInteger('birth_weight_g')->nullable();
            $table->unsignedTinyInteger('apgar_1')->nullable();
            $table->unsignedTinyInteger('apgar_5')->nullable();
            $table->string('outcome', 30); // live_birth | fresh_stillbirth | macerated_stillbirth | neonatal_death
            $table->boolean('resuscitated')->default(false);
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('postnatal_visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pregnancy_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            // Mother
            $table->unsignedSmallInteger('systolic')->nullable();
            $table->unsignedSmallInteger('diastolic')->nullable();
            $table->decimal('temperature', 4, 1)->nullable();
            $table->string('uterus', 30)->nullable();
            $table->string('lochia', 30)->nullable();
            $table->string('breastfeeding', 30)->nullable();
            $table->string('wound', 30)->nullable();
            $table->boolean('mood_concern')->default(false);
            $table->string('family_planning')->nullable();
            // Baby
            $table->unsignedSmallInteger('baby_weight_g')->nullable();
            $table->string('cord', 30)->nullable();
            $table->boolean('jaundice')->default(false);
            $table->text('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('vaccines', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name');
            $table->string('dose_label', 30)->nullable(); // e.g. "Dose 1", "Birth"
            $table->unsignedSmallInteger('age_days');     // recommended age
            $table->string('route', 20)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('immunizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vaccine_id')->constrained();
            $table->date('given_on');
            $table->string('batch_number', 50)->nullable();
            $table->string('site', 30)->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('given_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['patient_id', 'vaccine_id']);
        });
    }

    public function down(): void
    {
        foreach (['immunizations', 'vaccines', 'postnatal_visits', 'babies', 'deliveries', 'partograph_entries', 'anc_visits', 'pregnancies'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('patients', fn (Blueprint $t) => $t->dropConstrainedForeignId('mother_id'));
    }
};
