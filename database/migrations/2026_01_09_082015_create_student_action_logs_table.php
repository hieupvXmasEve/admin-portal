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
        Schema::create('student_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->string('action_type', 50);

            // Common fields
            $table->text('reason');
            $table->text('notes')->nullable();
            $table->date('signed_at')->nullable();
            $table->boolean('missing_documents')->default(false);
            $table->foreignId('changed_by_user_id')->constrained('users');

            // Semester references (nullable, used by specific action types)
            $table->foreignId('from_semester_id')->nullable()->constrained('semesters');
            $table->foreignId('return_semester_id')->nullable()->constrained('semesters');
            $table->foreignId('intended_intake_semester_id')->nullable()->constrained('semesters');
            $table->foreignId('dropout_semester_id')->nullable()->constrained('semesters');
            $table->foreignId('effective_semester_id')->nullable()->constrained('semesters');

            // Campus transfer fields
            $table->foreignId('from_campus_id')->nullable()->constrained('campuses');
            $table->foreignId('to_campus_id')->nullable()->constrained('campuses');
            $table->dateTime('effective_at')->nullable();

            // Snapshot of student state at time of action
            $table->string('previous_status', 50)->nullable();
            $table->string('new_status', 50)->nullable();
            $table->unsignedBigInteger('previous_campus_id')->nullable();

            $table->timestamps();

            // Indexes for query performance
            $table->index('action_type');
            $table->index('created_at');
            $table->index(['student_id', 'action_type']);
            $table->index(['created_at', 'action_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('student_action_logs');
    }
};
