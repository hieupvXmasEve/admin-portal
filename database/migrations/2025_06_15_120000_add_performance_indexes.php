<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add performance indexes for the academic system
     */
    public function up(): void
    {
        // Students table indexes
        Schema::table('students', function (Blueprint $table) {
            $table->index(['status'], 'idx_students_status');
            $table->index(['campus_id', 'status'], 'idx_students_campus_status');
            $table->index(['program_id', 'status'], 'idx_students_program_status');
            $table->index(['curriculum_version_id'], 'idx_students_curriculum_version');
            $table->index(['admission_date'], 'idx_students_admission_date');
        });

        // Enrollments table indexes
        Schema::table('enrollments', function (Blueprint $table) {
            $table->index(['student_id', 'semester_id'], 'idx_enrollments_student_semester');
            $table->index(['semester_id', 'status'], 'idx_enrollments_semester_status');
            $table->index(['curriculum_version_id'], 'idx_enrollments_curriculum_version');
            $table->index(['semester_number'], 'idx_enrollments_semester_number');
        });

        // Course Registrations table indexes
        Schema::table('course_registrations', function (Blueprint $table) {
            $table->index(['student_id', 'semester_id'], 'idx_course_reg_student_semester');
            $table->index(['course_offering_id', 'registration_status'], 'idx_course_reg_offering_status');
            $table->index(['semester_id', 'registration_status'], 'idx_course_reg_semester_status');
            $table->index(['registration_date'], 'idx_course_reg_date');
        });

        // Course Offerings table indexes
        Schema::table('course_offerings', function (Blueprint $table) {
            // Check if curriculum_unit_id column exists, otherwise use unit_id
            if (Schema::hasColumn('course_offerings', 'curriculum_unit_id')) {
                $table->index(['semester_id', 'curriculum_unit_id'], 'idx_course_off_semester_unit');
                $table->index(['curriculum_unit_id', 'is_active'], 'idx_course_off_unit_active');
            } elseif (Schema::hasColumn('course_offerings', 'unit_id')) {
                $table->index(['semester_id', 'unit_id'], 'idx_course_off_semester_unit');
                $table->index(['unit_id', 'is_active'], 'idx_course_off_unit_active');
            }
            $table->index(['lecture_id'], 'idx_course_off_lecture');
            $table->index(['max_capacity', 'current_enrollment'], 'idx_course_off_capacity');
        });

        // Academic Records table indexes
        Schema::table('academic_records', function (Blueprint $table) {
            $table->index(['student_id', 'semester_id'], 'idx_academic_rec_student_semester');
            $table->index(['unit_id', 'grade_status'], 'idx_academic_rec_unit_grade_status');
            $table->index(['semester_id', 'grade_status'], 'idx_academic_rec_semester_grade_status');
            $table->index(['final_letter_grade'], 'idx_academic_rec_letter_grade');
            $table->index(['excluded_from_gpa'], 'idx_academic_rec_excluded_gpa');
        });

        // GPA Calculations table indexes
        Schema::table('gpa_calculations', function (Blueprint $table) {
            $table->index(['student_id', 'calculation_type'], 'idx_gpa_calc_student_type');
            $table->index(['semester_id', 'calculation_type'], 'idx_gpa_calc_semester_type');
            $table->index(['program_id'], 'idx_gpa_calc_program');
            $table->index(['academic_standing'], 'idx_gpa_calc_academic_standing');
        });

        // Academic Holds table indexes
        Schema::table('academic_holds', function (Blueprint $table) {
            $table->index(['student_id', 'status'], 'idx_academic_holds_student_active');
            $table->index(['hold_type', 'status'], 'idx_academic_holds_type_active');
            $table->index(['placed_date'], 'idx_academic_holds_effective_date');
            $table->index(['resolved_date'], 'idx_academic_holds_resolved_date');
        });

        // Prerequisite Groups and Conditions indexes
        Schema::table('unit_prerequisite_groups', function (Blueprint $table) {
            $table->index(['unit_id'], 'idx_prereq_groups_unit');
        });

        Schema::table('unit_prerequisite_conditions', function (Blueprint $table) {
            $table->index(['group_id'], 'idx_prereq_conditions_group');
            $table->index(['required_unit_id'], 'idx_prereq_conditions_required_unit');
            $table->index(['type'], 'idx_prereq_conditions_type');
        });

        // Class Sessions table indexes
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->index(['course_offering_id', 'session_date'], 'idx_class_sessions_offering_date');
            $table->index(['session_date', 'start_time'], 'idx_class_sessions_datetime');
            $table->index(['room_id'], 'idx_class_sessions_room');
        });

        // Attendances table indexes
        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['student_id', 'class_session_id'], 'idx_attendances_student_session');
            $table->index(['class_session_id', 'status'], 'idx_attendances_session_status');
            $table->index(['status'], 'idx_attendances_status');
        });

        // Assessment Component Detail Scores indexes
        Schema::table('assessment_component_detail_scores', function (Blueprint $table) {
            $table->index(['student_id', 'assessment_component_detail_id'], 'idx_scores_student_component');
            $table->index(['assessment_component_detail_id'], 'idx_scores_component_detail');
            $table->index(['submitted_at'], 'idx_scores_submitted_at');
        });

        // Curriculum related indexes
        Schema::table('curriculum_units', function (Blueprint $table) {
            $table->index(['curriculum_version_id', 'semester_number'], 'idx_curriculum_units_version_semester');
            $table->index(['unit_id'], 'idx_curriculum_units_unit');
            $table->index(['is_required'], 'idx_curriculum_units_required');
        });

        Schema::table('curriculum_versions', function (Blueprint $table) {
            $table->index(['specialization_id', 'semester_id'], 'idx_curriculum_versions_spec_active');
            $table->index(['created_at'], 'idx_curriculum_versions_effective_date');
        });

        // Semesters table indexes
        Schema::table('semesters', function (Blueprint $table) {
            $table->index(['is_active'], 'idx_semesters_active');
            $table->index(['start_date', 'end_date'], 'idx_semesters_date_range');
            $table->index(['enrollment_start_date', 'enrollment_end_date'], 'idx_semesters_enrollment_period');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Students table indexes
        Schema::table('students', function (Blueprint $table) {
            $table->dropIndex('idx_students_status');
            $table->dropIndex('idx_students_campus_status');
            $table->dropIndex('idx_students_program_status');
            $table->dropIndex('idx_students_curriculum_version');
            $table->dropIndex('idx_students_admission_date');
        });

        // Enrollments table indexes
        Schema::table('enrollments', function (Blueprint $table) {
            $table->dropIndex('idx_enrollments_student_semester');
            $table->dropIndex('idx_enrollments_semester_status');
            $table->dropIndex('idx_enrollments_curriculum_version');
            $table->dropIndex('idx_enrollments_semester_number');
        });

        // Course Registrations table indexes
        Schema::table('course_registrations', function (Blueprint $table) {
            $table->dropIndex('idx_course_reg_student_semester');
            $table->dropIndex('idx_course_reg_offering_status');
            $table->dropIndex('idx_course_reg_semester_status');
            $table->dropIndex('idx_course_reg_date');
        });

        // Course Offerings table indexes
        Schema::table('course_offerings', function (Blueprint $table) {
            // Drop indexes that may exist
            if (Schema::hasIndex('course_offerings', 'idx_course_off_semester_unit')) {
                $table->dropIndex('idx_course_off_semester_unit');
            }
            if (Schema::hasIndex('course_offerings', 'idx_course_off_unit_active')) {
                $table->dropIndex('idx_course_off_unit_active');
            }
            $table->dropIndex('idx_course_off_lecture');
            $table->dropIndex('idx_course_off_capacity');
        });

        // Academic Records table indexes
        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropIndex('idx_academic_rec_student_semester');
            $table->dropIndex('idx_academic_rec_unit_grade_status');
            $table->dropIndex('idx_academic_rec_semester_grade_status');
            $table->dropIndex('idx_academic_rec_letter_grade');
            $table->dropIndex('idx_academic_rec_excluded_gpa');
        });

        // GPA Calculations table indexes
        Schema::table('gpa_calculations', function (Blueprint $table) {
            $table->dropIndex('idx_gpa_calc_student_type');
            $table->dropIndex('idx_gpa_calc_semester_type');
            $table->dropIndex('idx_gpa_calc_program');
            $table->dropIndex('idx_gpa_calc_academic_standing');
        });

        // Academic Holds table indexes
        Schema::table('academic_holds', function (Blueprint $table) {
            $table->dropIndex('idx_academic_holds_student_active');
            $table->dropIndex('idx_academic_holds_type_active');
            $table->dropIndex('idx_academic_holds_effective_date');
            $table->dropIndex('idx_academic_holds_resolved_date');
        });

        // Prerequisite Groups and Conditions indexes
        Schema::table('unit_prerequisite_groups', function (Blueprint $table) {
            $table->dropIndex('idx_prereq_groups_unit');
        });

        Schema::table('unit_prerequisite_conditions', function (Blueprint $table) {
            $table->dropIndex('idx_prereq_conditions_group');
            $table->dropIndex('idx_prereq_conditions_required_unit');
            $table->dropIndex('idx_prereq_conditions_type');
        });

        // Class Sessions table indexes
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_class_sessions_offering_date');
            $table->dropIndex('idx_class_sessions_datetime');
            $table->dropIndex('idx_class_sessions_room');
        });

        // Attendances table indexes
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_student_session');
            $table->dropIndex('idx_attendances_session_status');
            $table->dropIndex('idx_attendances_status');
        });

        // Assessment Component Detail Scores indexes
        Schema::table('assessment_component_detail_scores', function (Blueprint $table) {
            $table->dropIndex('idx_scores_student_component');
            $table->dropIndex('idx_scores_component_detail');
            $table->dropIndex('idx_scores_submitted_at');
        });

        // Curriculum related indexes
        Schema::table('curriculum_units', function (Blueprint $table) {
            $table->dropIndex('idx_curriculum_units_version_semester');
            $table->dropIndex('idx_curriculum_units_unit');
            $table->dropIndex('idx_curriculum_units_required');
        });

        Schema::table('curriculum_versions', function (Blueprint $table) {
            $table->dropIndex('idx_curriculum_versions_spec_active');
            $table->dropIndex('idx_curriculum_versions_effective_date');
        });

        // Semesters table indexes
        Schema::table('semesters', function (Blueprint $table) {
            $table->dropIndex('idx_semesters_active');
            $table->dropIndex('idx_semesters_date_range');
            $table->dropIndex('idx_semesters_enrollment_period');
        });
    }
};
