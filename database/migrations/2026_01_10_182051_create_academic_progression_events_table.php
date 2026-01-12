<?php

declare(strict_types=1);

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
        Schema::create('academic_progression_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('event_type', 50);
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();
            $table->dateTime('effective_at');
            $table->string('trigger_source', 30);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();

            // Before/After snapshot fields
            $table->string('from_course_stage', 30)->nullable();
            $table->string('to_course_stage', 30)->nullable();
            $table->unsignedTinyInteger('from_english_level')->nullable();
            $table->unsignedTinyInteger('to_english_level')->nullable();

            // Link to IELTS certificate for audit
            $table->foreignId('ielts_certificate_id')->nullable()->constrained('ielts_certificates')->nullOnDelete();

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'effective_at']);
            $table->index(['semester_id', 'event_type']);
            $table->index('event_type');
            $table->index('trigger_source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academic_progression_events');
    }
};
