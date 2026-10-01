<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('imaging_tests', function (Blueprint $table) {
            // Pre-filled findings for a normal study; the radiologist edits what differs.
            $table->text('report_template')->nullable()->after('modality');
        });

        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->dateTime('scheduled_for')->nullable()->after('status');
            $table->timestamp('performed_at')->nullable()->after('scheduled_for');
            $table->foreignId('performed_by')->nullable()->after('performed_at')->constrained('users')->nullOnDelete();
            $table->text('technique')->nullable()->after('performed_by');
            $table->text('findings')->nullable()->after('technique');
            $table->text('impression')->nullable()->after('findings');
            $table->foreignId('reported_by')->nullable()->after('impression')->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable()->after('reported_by');
        });

        Schema::create('imaging_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imaging_order_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size');
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('imaging_attachments');

        Schema::table('imaging_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('performed_by');
            $table->dropConstrainedForeignId('reported_by');
            $table->dropColumn(['scheduled_for', 'performed_at', 'technique', 'findings', 'impression', 'completed_at']);
        });

        Schema::table('imaging_tests', function (Blueprint $table) {
            $table->dropColumn('report_template');
        });
    }
};
