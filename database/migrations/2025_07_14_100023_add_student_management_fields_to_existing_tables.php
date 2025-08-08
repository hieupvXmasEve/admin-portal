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
        // Add retake tracking to course_registrations (check if columns don't exist first)
        Schema::table('course_registrations', function (Blueprint $table) {
            if (! Schema::hasColumn('course_registrations', 'original_registration_id')) {
                $table->foreignId('original_registration_id')->nullable()->constrained('course_registrations')->onDelete('set null');
            }
            if (! Schema::hasColumn('course_registrations', 'retake_reason')) {
                $table->text('retake_reason')->nullable();
            }
        });

        // Add status tracking to students (check if columns don't exist first)
        Schema::table('students', function (Blueprint $table) {
            if (! Schema::hasColumn('students', 'academic_status')) {
                $table->enum('academic_status', ['active', 'inactive', 'graduated', 'suspended', 'withdrawn'])->default('active');
            }
            if (! Schema::hasColumn('students', 'status_change_date')) {
                $table->date('status_change_date')->nullable();
            }
            if (! Schema::hasColumn('students', 'status_reason')) {
                $table->text('status_reason')->nullable();
            }
            if (! Schema::hasColumn('students', 'status_changed_by')) {
                $table->foreignId('status_changed_by')->nullable()->constrained('users')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_registrations', function (Blueprint $table) {
            $table->dropColumn(['is_retake', 'original_registration_id', 'retake_reason', 'attempt_number']);
        });

        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['academic_status', 'status_change_date', 'status_reason', 'status_changed_by']);
        });
    }
};
