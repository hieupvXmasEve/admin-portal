<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('student_action_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_action_log_id')->constrained('student_action_logs')->cascadeOnDelete();
            $table->foreignId('upload_record_id')->constrained('upload_records')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['student_action_log_id', 'upload_record_id'], 'action_upload_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_action_attachments');
    }
};
