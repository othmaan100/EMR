<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->string('specialty', 20)->default('general')->after('requires_triage'); // general | dental | eye | physio
        });

        // One row per finding or treatment on a tooth (FDI numbering).
        Schema::create('dental_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('tooth')->nullable()->index(); // null = whole mouth (e.g. scaling)
            $table->string('surfaces', 10)->nullable();
            $table->string('condition', 30)->nullable();
            $table->string('status', 20)->default('existing')->index(); // existing | planned | completed | cancelled
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete(); // treatment (billable)
            $table->string('notes', 500)->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('eye_exams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('va_right', 10)->nullable();
            $table->string('va_left', 10)->nullable();
            $table->string('va_right_corrected', 10)->nullable(); // pinhole or with glasses
            $table->string('va_left_corrected', 10)->nullable();
            $table->decimal('iop_right', 4, 1)->nullable();       // mmHg
            $table->decimal('iop_left', 4, 1)->nullable();
            foreach (['right', 'left'] as $eye) {
                $table->decimal("sph_{$eye}", 5, 2)->nullable();
                $table->decimal("cyl_{$eye}", 5, 2)->nullable();
                $table->unsignedSmallInteger("axis_{$eye}")->nullable();
                $table->decimal("add_{$eye}", 4, 2)->nullable();
                $table->text("anterior_{$eye}")->nullable();
                $table->text("fundus_{$eye}")->nullable();
            }
            $table->unsignedTinyInteger('pd')->nullable();          // pupillary distance, mm
            $table->string('diagnosis')->nullable();
            $table->text('plan')->nullable();
            $table->boolean('spectacles_prescribed')->default(false);
            $table->string('lens_notes')->nullable();               // e.g. photochromic, bifocal
            $table->foreignId('examined_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // A course of physiotherapy (assessment → sessions → discharge).
        Schema::create('physio_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('region', 100);
            $table->text('complaint');
            $table->text('assessment')->nullable();
            $table->unsignedTinyInteger('pain_initial')->nullable(); // 0–10
            $table->text('goals')->nullable();
            $table->text('plan')->nullable();
            $table->unsignedTinyInteger('sessions_planned')->nullable();
            $table->string('status', 20)->default('active')->index(); // active | discharged
            $table->string('outcome', 20)->nullable();
            $table->text('discharge_notes')->nullable();
            $table->timestamp('discharged_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('physio_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('physio_episode_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->date('session_date');
            $table->json('treatments');
            $table->unsignedTinyInteger('pain_before')->nullable();
            $table->unsignedTinyInteger('pain_after')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('therapist_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('physio_sessions');
        Schema::dropIfExists('physio_episodes');
        Schema::dropIfExists('eye_exams');
        Schema::dropIfExists('dental_findings');
        Schema::table('clinics', fn (Blueprint $t) => $t->dropColumn('specialty'));
    }
};
