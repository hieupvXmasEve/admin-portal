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
        Schema::create('student_form_surveys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_survey_id')->constrained('form_surveys')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('not_started');
            $table->foreignId('response_id')->nullable()->constrained('responses')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            // Ensure one survey task per student per form_survey (idempotent)
            $table->unique(['form_survey_id', 'student_id']);
            $table->index(['student_id', 'status']);
            $table->index(['form_survey_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_form_surveys');
    }
};
