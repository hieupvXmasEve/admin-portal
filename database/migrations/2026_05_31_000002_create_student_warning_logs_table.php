<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_warning_logs', function (Blueprint $table) {
            $table->id();
            $table->string('dedupe_key', 64)->unique();
            $table->string('warning_type', 80);
            $table->foreignId('campus_id')->nullable()->constrained('campuses')->nullOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('course_offering_id')->nullable()->constrained('course_offerings')->nullOnDelete();
            $table->foreignId('class_session_id')->nullable()->constrained('class_sessions')->nullOnDelete();
            $table->foreignId('gpa_calculation_id')->nullable()->constrained('gpa_calculations')->nullOnDelete();
            $table->unsignedInteger('absence_count')->nullable();
            $table->unsignedInteger('warning_absences')->nullable();
            $table->unsignedInteger('allowed_absences')->nullable();
            $table->unsignedInteger('total_sessions')->nullable();
            $table->json('threshold_snapshot')->nullable();
            $table->json('channels')->nullable();
            $table->string('message_title');
            $table->text('message_body');
            $table->uuid('notification_event_id')->nullable();
            $table->string('status', 30)->default('queued');
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['student_id', 'warning_type', 'created_at']);
            $table->index(['course_offering_id', 'warning_type']);
            $table->index(['gpa_calculation_id', 'warning_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_warning_logs');
    }
};
