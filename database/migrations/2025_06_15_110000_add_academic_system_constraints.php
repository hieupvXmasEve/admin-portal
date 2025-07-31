<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Skip constraints for SQLite (used in testing)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Add check constraints to ensure data integrity

        // Rooms table constraints
        DB::statement('ALTER TABLE rooms ADD CONSTRAINT check_capacity_positive CHECK (capacity > 0)');
        // DB::statement('ALTER TABLE rooms ADD CONSTRAINT check_exam_capacity_valid CHECK (exam_capacity IS NULL OR exam_capacity <= capacity)');
        DB::statement('ALTER TABLE rooms ADD CONSTRAINT check_available_times CHECK (available_from < available_until)');

        // Room bookings constraints
        DB::statement('ALTER TABLE room_bookings ADD CONSTRAINT check_booking_times CHECK (start_time < end_time)');
        // DB::statement('ALTER TABLE room_bookings ADD CONSTRAINT check_expected_attendees CHECK (expected_attendees IS NULL OR expected_attendees > 0)'); // Column doesn't exist
        DB::statement('ALTER TABLE room_bookings ADD CONSTRAINT check_recurrence_end_date CHECK (recurrence_end_date IS NULL OR recurrence_end_date >= booking_date)');

        // Class sessions constraints
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT check_sessions_times CHECK (start_time < end_time)');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT check_sessions_expected_attendees_positive CHECK (expected_attendees IS NULL OR expected_attendees > 0)');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT check_sessions_actual_attendees_valid CHECK (actual_attendees IS NULL OR actual_attendees >= 0)');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT check_sessions_attendance_percentage CHECK (attendance_percentage IS NULL OR (attendance_percentage >= 0 AND attendance_percentage <= 100))');
        DB::statement('ALTER TABLE class_sessions ADD CONSTRAINT check_sessions_assessment_weight CHECK (assessment_weight IS NULL OR (assessment_weight >= 0 AND assessment_weight <= 100))');

        // Attendances constraints
        DB::statement('ALTER TABLE attendances ADD CONSTRAINT check_attendances_minutes_late_positive CHECK (minutes_late >= 0)');
        DB::statement('ALTER TABLE attendances ADD CONSTRAINT check_attendances_minutes_present_positive CHECK (minutes_present IS NULL OR minutes_present >= 0)');
        DB::statement('ALTER TABLE attendances ADD CONSTRAINT check_attendances_participation_score CHECK (participation_score IS NULL OR (participation_score >= 0 AND participation_score <= 10))');

        // Assessment components constraints
        DB::statement('ALTER TABLE assessment_components ADD CONSTRAINT check_weight_percentage CHECK (weight >= 0 AND weight <= 100)');
        DB::statement('ALTER TABLE assessment_components ADD CONSTRAINT check_late_penalty_percentage CHECK (late_penalty_percentage >= 0 AND late_penalty_percentage <= 100)');
        DB::statement('ALTER TABLE assessment_components ADD CONSTRAINT check_file_size_positive CHECK (max_file_size_mb IS NULL OR max_file_size_mb > 0)');
        DB::statement('ALTER TABLE assessment_components ADD CONSTRAINT check_max_submissions_positive CHECK (max_submissions >= 1)');
        DB::statement('ALTER TABLE assessment_components ADD CONSTRAINT check_group_size_valid CHECK (
            (min_group_size IS NULL OR min_group_size >= 1) AND
            (max_group_size IS NULL OR max_group_size >= 1) AND
            (min_group_size IS NULL OR max_group_size IS NULL OR min_group_size <= max_group_size)
        )');

        // Assessment component detail scores constraints
        DB::statement('ALTER TABLE assessment_component_detail_scores ADD CONSTRAINT check_scores_percentage_score CHECK (percentage_score IS NULL OR (percentage_score >= 0 AND percentage_score <= 100))');
        DB::statement('ALTER TABLE assessment_component_detail_scores ADD CONSTRAINT check_scores_gpa_points CHECK (gpa_points IS NULL OR (gpa_points >= 0 AND gpa_points <= 4))');
        DB::statement('ALTER TABLE assessment_component_detail_scores ADD CONSTRAINT check_scores_submission_attempt_positive CHECK (submission_attempt >= 1)');
        DB::statement('ALTER TABLE assessment_component_detail_scores ADD CONSTRAINT check_scores_minutes_late_positive CHECK (minutes_late >= 0)');
        DB::statement('ALTER TABLE assessment_component_detail_scores ADD CONSTRAINT check_scores_late_penalty_applied CHECK (late_penalty_applied >= 0 AND late_penalty_applied <= 100)');
        DB::statement('ALTER TABLE assessment_component_detail_scores ADD CONSTRAINT check_scores_plagiarism_score CHECK (plagiarism_score IS NULL OR (plagiarism_score >= 0 AND plagiarism_score <= 100))');

        // Academic records constraints
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_final_percentage CHECK (final_percentage IS NULL OR (final_percentage >= 0 AND final_percentage <= 100))');
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_grade_points CHECK (grade_points IS NULL OR (grade_points >= 0 AND grade_points <= 4))');
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_credit_hours_positive CHECK (credit_hours > 0)');
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_credit_hours_earned CHECK (credit_hours_earned >= 0 AND credit_hours_earned <= credit_hours)');
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_quality_points CHECK (quality_points IS NULL OR quality_points >= 0)');
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_attendance_percentage CHECK (attendance_percentage IS NULL OR (attendance_percentage >= 0 AND attendance_percentage <= 100))');
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_attempt_number_positive CHECK (attempt_number >= 1)');
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_curve_adjustment CHECK (curve_adjustment >= -100 AND curve_adjustment <= 100)');

        // GPA calculations constraints
        DB::statement('ALTER TABLE gpa_calculations ADD CONSTRAINT check_gpa_valid CHECK (gpa IS NULL OR (gpa >= 0 AND gpa <= 4))');
        DB::statement('ALTER TABLE gpa_calculations ADD CONSTRAINT check_quality_points_positive CHECK (quality_points >= 0)');
        DB::statement('ALTER TABLE gpa_calculations ADD CONSTRAINT check_credit_hours_positive CHECK (
            credit_hours_attempted >= 0 AND
            credit_hours_earned >= 0 AND
            credit_hours_gpa >= 0 AND
            credit_hours_earned <= credit_hours_attempted
        )');
        DB::statement('ALTER TABLE gpa_calculations ADD CONSTRAINT check_course_counts CHECK (
            total_courses >= 0 AND
            completed_courses >= 0 AND
            failed_courses >= 0 AND
            withdrawn_courses >= 0 AND
            incomplete_courses >= 0 AND
            (completed_courses + failed_courses + withdrawn_courses + incomplete_courses) <= total_courses
        )');
        DB::statement('ALTER TABLE gpa_calculations ADD CONSTRAINT check_grade_counts CHECK (
            a_grades >= 0 AND b_grades >= 0 AND c_grades >= 0 AND d_grades >= 0 AND f_grades >= 0
        )');
        DB::statement('ALTER TABLE gpa_calculations ADD CONSTRAINT check_percentile CHECK (percentile IS NULL OR (percentile >= 0 AND percentile <= 100))');
        DB::statement('ALTER TABLE gpa_calculations ADD CONSTRAINT check_completion_percentage CHECK (completion_percentage IS NULL OR (completion_percentage >= 0 AND completion_percentage <= 100))');

        // Add additional performance indexes

        // Cross-table indexes for complex queries
        // Schema::table('class_sessions', function (Blueprint $table) {
        //     $table->index(['session_date', 'room_id', 'start_time', 'end_time'], 'daily_room_schedule_idx');
        //     $table->index(['instructor_id', 'session_date', 'session_type'], 'instructor_daily_schedule_idx');
        // });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['student_id', 'check_in_time', 'status'], 'student_attendance_timeline_idx');
            $table->index(['recorded_by_lecture_id', 'created_at'], 'recorded_by_lecture_idx');
        });

        Schema::table('assessment_component_detail_scores', function (Blueprint $table) {
            $table->index(['student_id', 'graded_at', 'score_status', 'points_earned'], 'student_score_timeline_idx');
            $table->index(['graded_by_lecture_id', 'graded_at'], 'graded_by_lecture_idx');
        });

        Schema::table('academic_records', function (Blueprint $table) {
            $table->index(['student_id', 'grade_finalized_date', 'final_letter_grade'], 'student_final_grades_idx');
            $table->index(['semester_id', 'unit_id', 'completion_status'], 'semester_unit_completion_idx');
        });

        // Add full-text search indexes for descriptions and notes
        DB::statement('ALTER TABLE rooms ADD FULLTEXT(description, usage_guidelines, booking_notes)');
        DB::statement('ALTER TABLE room_bookings ADD FULLTEXT(title, description, special_requirements)');
        DB::statement('ALTER TABLE class_sessions ADD FULLTEXT(session_title, session_description, student_instructions)');
        DB::statement('ALTER TABLE assessment_components ADD FULLTEXT(name, description, grading_instructions)');
    }

    public function down(): void
    {
        // Remove check constraints
        $constraints = [
            'rooms' => [
                'check_capacity_positive',
                // 'check_exam_capacity_valid',
                'check_available_times'
            ],
            'room_bookings' => [
                'check_booking_times',
                // 'check_expected_attendees', // Column doesn't exist
                'check_recurrence_end_date'
            ],
            'class_sessions' => [
                'check_sessions_times',
                'check_sessions_expected_attendees_positive',
                'check_sessions_actual_attendees_valid',
                'check_sessions_attendance_percentage',
                'check_sessions_assessment_weight'
            ],
            'attendances' => [
                'check_attendances_minutes_late_positive',
                'check_attendances_minutes_present_positive',
                'check_attendances_participation_score'
            ],
            'assessment_components' => [
                'check_weight_percentage',
                'check_late_penalty_percentage',
                'check_file_size_positive',
                'check_max_submissions_positive',
                'check_group_size_valid'
            ],
            'assessment_component_detail_scores' => [
                'check_scores_percentage_score',
                'check_scores_gpa_points',
                'check_scores_submission_attempt_positive',
                'check_scores_minutes_late_positive',
                'check_scores_late_penalty_applied',
                'check_scores_plagiarism_score'
            ],
            'academic_records' => [
                'check_records_final_percentage',
                'check_records_grade_points',
                'check_records_credit_hours_positive',
                'check_records_credit_hours_earned',
                'check_records_quality_points',
                'check_records_attendance_percentage',
                'check_records_attempt_number_positive',
                'check_records_curve_adjustment'
            ],
            'gpa_calculations' => [
                'check_gpa_valid',
                'check_quality_points_positive',
                'check_credit_hours_positive',
                'check_course_counts',
                'check_grade_counts',
                'check_percentile',
                'check_completion_percentage'
            ]
        ];

        foreach ($constraints as $table => $tableConstraints) {
            foreach ($tableConstraints as $constraint) {
                try {
                    DB::statement("ALTER TABLE {$table} DROP CONSTRAINT {$constraint}");
                } catch (Exception $e) {
                    // Constraint might not exist, continue
                }
            }
        }

        // Remove additional indexes
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex('daily_room_schedule_idx');
            $table->dropIndex('instructor_daily_schedule_idx');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('student_attendance_timeline_idx');
            $table->dropIndex('recorded_by_lecture_idx');
        });

        Schema::table('assessment_component_detail_scores', function (Blueprint $table) {
            $table->dropIndex('student_score_timeline_idx');
            $table->dropIndex('graded_by_lecture_idx');
        });

        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropIndex('student_final_grades_idx');
            $table->dropIndex('semester_unit_completion_idx');
        });
    }
};
