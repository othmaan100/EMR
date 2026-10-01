<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Gap-free counters (patient numbers now; invoices/receipts later).
        Schema::create('sequences', function (Blueprint $table) {
            $table->string('name', 50)->primary();
            $table->unsignedBigInteger('next_value')->default(1);
        });

        Schema::create('insurance_providers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code', 20)->unique();
            $table->string('type', 30)->default('insurance'); // insurance | corporate
            $table->string('contact_person')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('hospital_number', 30)->unique();
            $table->string('legacy_number', 50)->nullable()->index();

            // Identity
            $table->string('title', 20)->nullable();
            $table->string('first_name', 100);
            $table->string('middle_name', 100)->nullable();
            $table->string('last_name', 100);
            $table->string('gender', 10);
            $table->date('date_of_birth')->nullable();
            $table->boolean('dob_estimated')->default(false);
            $table->string('marital_status', 20)->nullable();
            $table->string('national_id', 50)->nullable()->index();
            $table->string('occupation', 100)->nullable();
            $table->string('religion', 50)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('photo')->nullable();

            // Contact
            $table->string('phone', 30)->nullable()->index();
            $table->string('alt_phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('state', 100)->nullable();
            $table->string('country', 100)->nullable();

            // Clinical basics
            $table->string('blood_group', 5)->nullable();
            $table->string('genotype', 5)->nullable();
            $table->text('allergies')->nullable();

            // Next of kin
            $table->string('nok_name', 150)->nullable();
            $table->string('nok_relationship', 50)->nullable();
            $table->string('nok_phone', 30)->nullable();
            $table->string('nok_address')->nullable();

            // Payment
            $table->string('payment_type', 20)->default('self_pay');
            $table->foreignId('insurance_provider_id')->nullable()->constrained()->nullOnDelete();
            $table->string('insurance_number', 50)->nullable()->index();
            $table->date('insurance_expiry')->nullable();

            $table->text('notes')->nullable();
            $table->boolean('is_deceased')->default(false);
            $table->date('date_of_death')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
        Schema::dropIfExists('insurance_providers');
        Schema::dropIfExists('sequences');
    }
};
