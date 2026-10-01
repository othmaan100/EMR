<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 30);
            $table->text('body');
            $table->string('type', 30)->index();       // appointment_reminder | results_ready | immunization_reminder | test
            $table->string('status', 10)->index();     // queued | sent | failed | logged
            $table->string('provider', 20)->nullable();
            $table->string('provider_message_id', 100)->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('dedupe_key', 100)->nullable()->unique(); // stops duplicate reminders
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            // Pharmacy pay-first: quantity priced and sent to the cashier.
            $table->unsignedInteger('billed_quantity')->default(0)->after('quantity_dispensed');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_items', fn (Blueprint $t) => $t->dropColumn('billed_quantity'));
        Schema::dropIfExists('sms_messages');
    }
};
