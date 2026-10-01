<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One row per uploaded file: validated first, then run (or cancelled).
        Schema::create('data_imports', function (Blueprint $table) {
            $table->id();
            $table->string('type', 40)->index();
            $table->string('original_name');
            $table->string('stored_path')->nullable();     // private disk; removed after run/cancel
            $table->string('mode', 20)->default('create'); // create | update
            $table->json('options')->nullable();
            $table->string('status', 20)->index();         // validated | completed | failed | cancelled
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('create_rows')->default(0);
            $table->unsignedInteger('update_rows')->default(0);
            $table->unsignedInteger('skip_rows')->default(0);
            $table->unsignedInteger('error_rows')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->json('errors')->nullable();            // [{row, messages, data}] (capped)
            $table->json('warnings')->nullable();          // file-level notes (unknown columns…)
            $table->string('message')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_imports');
    }
};
