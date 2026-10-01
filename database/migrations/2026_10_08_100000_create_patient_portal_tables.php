<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Patient portal logins: patients sign in with hospital number + password.
        Schema::create('patient_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('password')->nullable();                 // null until activated
            $table->string('activation_code')->nullable();          // hashed one-time code from the hospital
            $table->timestamp('activation_expires_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->string('source', 10)->default('staff')->after('status'); // staff | portal
        });
    }

    public function down(): void
    {
        Schema::table('appointments', fn (Blueprint $t) => $t->dropColumn('source'));
        Schema::dropIfExists('patient_accounts');
    }
};
