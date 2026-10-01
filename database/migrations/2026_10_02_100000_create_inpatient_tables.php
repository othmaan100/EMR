<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wards', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 10)->unique();
            $table->string('type', 30);
            $table->string('gender', 10)->default('any'); // any | male | female
            $table->foreignId('department_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete(); // daily bed charge
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('beds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ward_id')->constrained()->cascadeOnDelete();
            $table->string('label', 20);
            $table->string('status', 20)->default('available'); // available | occupied | cleaning | out_of_service
            $table->timestamps();
            $table->unique(['ward_id', 'label']);
        });

        Schema::create('admissions', function (Blueprint $table) {
            $table->id();
            $table->string('admission_number', 30)->unique();
            $table->foreignId('patient_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('ward_id')->constrained();
            $table->foreignId('bed_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('admitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('admitted_at');
            $table->text('reason');
            $table->string('status', 20)->default('admitted')->index(); // admitted | discharged
            $table->date('bed_charged_until')->nullable();
            // Discharge
            $table->timestamp('discharged_at')->nullable();
            $table->foreignId('discharged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('discharge_type', 20)->nullable();
            $table->text('final_diagnosis')->nullable();
            $table->text('discharge_summary')->nullable();
            $table->text('discharge_medications')->nullable();
            $table->text('follow_up')->nullable();
            $table->timestamps();
        });

        Schema::create('bed_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('from_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->foreignId('to_bed_id')->nullable()->constrained('beds')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('admission_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20); // ward_round | progress | nursing
            $table->text('note');
            $table->timestamps();
        });

        // Orders placed on the ward are billed to the admission.
        foreach (['lab_orders', 'imaging_orders', 'prescriptions'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->foreignId('admission_id')->nullable()->after('visit_id')->constrained()->nullOnDelete();
            });
        }

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->timestamp('stopped_at')->nullable();
            $table->foreignId('stopped_by')->nullable()->constrained('users')->nullOnDelete();
        });

        // Medication administration record.
        Schema::create('medication_administrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('prescription_item_id')->constrained()->cascadeOnDelete();
            $table->string('status', 10); // given | refused | held
            $table->string('dose_given', 50)->nullable();
            $table->string('note')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('administered_at');
            $table->timestamps();
        });

        Schema::table('bills', function (Blueprint $table) {
            $table->foreignId('admission_id')->nullable()->unique()->after('visit_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bills', fn (Blueprint $t) => $t->dropConstrainedForeignId('admission_id'));
        Schema::dropIfExists('medication_administrations');
        Schema::table('prescription_items', function (Blueprint $t) {
            $t->dropConstrainedForeignId('stopped_by');
            $t->dropColumn('stopped_at');
        });
        foreach (['lab_orders', 'imaging_orders', 'prescriptions'] as $name) {
            Schema::table($name, fn (Blueprint $t) => $t->dropConstrainedForeignId('admission_id'));
        }
        foreach (['admission_notes', 'bed_movements', 'admissions', 'beds', 'wards'] as $name) {
            Schema::dropIfExists($name);
        }
    }
};
