<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Dọn dẹp bảng course_registrations
        Schema::table('course_registrations', function (Blueprint $table) {
            // Drop indexes nếu còn tồn tại
            $indexes = DB::select("SHOW INDEX FROM course_registrations");
            $indexNames = array_column($indexes, 'Key_name');
            
            if (in_array('idx_course_reg_offering_status', $indexNames)) {
                $table->dropIndex('idx_course_reg_offering_status');
            }
            if (in_array('idx_course_reg_semester_status', $indexNames)) {
                $table->dropIndex('idx_course_reg_semester_status');
            }

            // Chỉ drop nếu cột còn tồn tại
            $columnsToDrop = [];
            foreach (['final_grade', 'grade_points', 'completion_date', 'attempt_number', 'is_retake', 'credit_hours'] as $col) {
                if (Schema::hasColumn('course_registrations', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        Schema::table('course_registrations', function (Blueprint $table) {
            // Cập nhật enum registration_status
            $col = DB::select("SHOW COLUMNS FROM course_registrations LIKE 'registration_status'")[0];
            if (str_contains($col->Type, 'completed')) {
                DB::statement("UPDATE course_registrations SET registration_status = 'confirmed' WHERE registration_status = 'completed'");
                DB::statement("ALTER TABLE course_registrations MODIFY COLUMN registration_status ENUM('registered', 'confirmed', 'dropped', 'withdrawn') NOT NULL DEFAULT 'registered'");
            }

            // Tạo lại các Index cho bảng registration
            $indexes = DB::select("SHOW INDEX FROM course_registrations");
            $indexNames = array_column($indexes, 'Key_name');
            
            if (!in_array('idx_course_reg_offering_status', $indexNames)) {
                $table->index(['course_offering_id', 'registration_status'], 'idx_course_reg_offering_status');
            }
            if (!in_array('idx_course_reg_semester_status', $indexNames)) {
                $table->index(['semester_id', 'registration_status'], 'idx_course_reg_semester_status');
            }
        });

        // 2. Dọn dẹp và chuẩn hóa bảng academic_records
        Schema::table('academic_records', function (Blueprint $table) {
            // Drop toàn bộ các Index cũ
            $indexes = DB::select("SHOW INDEX FROM academic_records");
            $indexNames = array_column($indexes, 'Key_name');
            
            foreach (['student_grade_completion_idx', 'program_semester_completion_idx', 'enrollment_completion_dates_idx', 'transcript_calc_idx', 'gpa_calc_idx', 'semester_unit_completion_idx'] as $idx) {
                if (in_array($idx, $indexNames)) {
                    $table->dropIndex($idx);
                }
            }

            // Drop CHECK constraints
            try { DB::statement('ALTER TABLE academic_records DROP CONSTRAINT check_records_credit_hours_earned'); } catch (\Exception $e) {}
            try { DB::statement('ALTER TABLE academic_records DROP CONSTRAINT check_records_credit_hours_positive'); } catch (\Exception $e) {}

            if (Schema::hasColumn('academic_records', 'enrollment_date')) {
                $table->dropColumn(['enrollment_date']);
            }

            // Đổi tên terminology chuẩn học thuật
            if (Schema::hasColumn('academic_records', 'credit_hours')) {
                $table->renameColumn('credit_hours', 'credit_points');
            }
            if (Schema::hasColumn('academic_records', 'credit_hours_earned')) {
                $table->renameColumn('credit_hours_earned', 'credit_points_earned');
            }
            if (Schema::hasColumn('academic_records', 'completion_status')) {
                $table->renameColumn('completion_status', 'outcome_status');
            }
        });

        // Cấu hình lại Academic Records với schema mới
        Schema::table('academic_records', function (Blueprint $table) {
            if (Schema::hasColumn('academic_records', 'credit_points')) {
                $table->decimal('credit_points', 5, 2)->change();
            }
            if (Schema::hasColumn('academic_records', 'credit_points_earned')) {
                $table->decimal('credit_points_earned', 5, 2)->change();
            }

            // Tạo lại Index với tên cột mới
            $indexes = DB::select("SHOW INDEX FROM academic_records");
            $indexNames = array_column($indexes, 'Key_name');
            
            if (!in_array('student_grade_outcome_idx', $indexNames)) {
                $table->index(['student_id', 'grade_status', 'outcome_status'], 'student_grade_outcome_idx');
            }
            if (!in_array('program_semester_outcome_idx', $indexNames)) {
                $table->index(['program_id', 'semester_id', 'outcome_status'], 'program_semester_outcome_idx');
            }
            if (!in_array('idx_academic_rec_completion_date', $indexNames)) {
                $table->index(['completion_date'], 'idx_academic_rec_completion_date');
            }
            if (!in_array('transcript_calc_idx', $indexNames)) {
                $table->index(['student_id', 'outcome_status', 'grade_finalized_date', 'credit_points'], 'transcript_calc_idx');
            }
            if (!in_array('gpa_calc_idx', $indexNames)) {
                $table->index(['student_id', 'excluded_from_gpa', 'quality_points', 'credit_points'], 'gpa_calc_idx');
            }
            if (!in_array('semester_unit_outcome_idx', $indexNames)) {
                $table->index(['semester_id', 'unit_id', 'outcome_status'], 'semester_unit_outcome_idx');
            }

            // Re-add CHECK constraints
            try {
                DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_credit_points_earned CHECK (credit_points_earned >= 0 AND credit_points_earned <= credit_points)');
            } catch (\Exception $e) {}
            try {
                DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_credit_points_positive CHECK (credit_points >= 0)');
            } catch (\Exception $e) {}
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        // 1. Khôi phục bảng course_registrations
        Schema::table('course_registrations', function (Blueprint $table) {
            $indexes = DB::select("SHOW INDEX FROM course_registrations");
            $indexNames = array_column($indexes, 'Key_name');
            
            if (in_array('idx_course_reg_offering_status', $indexNames)) {
                $table->dropIndex('idx_course_reg_offering_status');
            }
            if (in_array('idx_course_reg_semester_status', $indexNames)) {
                $table->dropIndex('idx_course_reg_semester_status');
            }
            
            if (!Schema::hasColumn('course_registrations', 'final_grade')) {
                $table->string('final_grade', 3)->nullable();
            }
            if (!Schema::hasColumn('course_registrations', 'grade_points')) {
                $table->decimal('grade_points', 3, 2)->nullable();
            }
            if (!Schema::hasColumn('course_registrations', 'completion_date')) {
                $table->timestamp('completion_date')->nullable();
            }
            if (!Schema::hasColumn('course_registrations', 'attempt_number')) {
                $table->integer('attempt_number')->default(1);
            }
            if (!Schema::hasColumn('course_registrations', 'is_retake')) {
                $table->boolean('is_retake')->default(false);
            }
            if (!Schema::hasColumn('course_registrations', 'credit_hours')) {
                $table->decimal('credit_hours', 4, 2)->nullable();
            }
            
            DB::statement("ALTER TABLE course_registrations MODIFY COLUMN registration_status ENUM('registered', 'confirmed', 'dropped', 'withdrawn', 'completed') NOT NULL DEFAULT 'registered'");
        });

        Schema::table('course_registrations', function (Blueprint $table) {
            $table->index(['course_offering_id', 'registration_status'], 'idx_course_reg_offering_status');
            $table->index(['semester_id', 'registration_status'], 'idx_course_reg_semester_status');
        });

        // 2. Khôi phục bảng academic_records
        Schema::table('academic_records', function (Blueprint $table) {
            $indexes = DB::select("SHOW INDEX FROM academic_records");
            $indexNames = array_column($indexes, 'Key_name');
            
            foreach (['student_grade_outcome_idx', 'program_semester_outcome_idx', 'idx_academic_rec_completion_date', 'transcript_calc_idx', 'gpa_calc_idx', 'semester_unit_outcome_idx'] as $idx) {
                if (in_array($idx, $indexNames)) {
                    $table->dropIndex($idx);
                }
            }

            try { DB::statement('ALTER TABLE academic_records DROP CONSTRAINT check_records_credit_points_earned'); } catch (\Exception $e) {}
            try { DB::statement('ALTER TABLE academic_records DROP CONSTRAINT check_records_credit_points_positive'); } catch (\Exception $e) {}

            if (Schema::hasColumn('academic_records', 'outcome_status')) {
                $table->renameColumn('outcome_status', 'completion_status');
            }
            if (Schema::hasColumn('academic_records', 'credit_points')) {
                $table->renameColumn('credit_points', 'credit_hours');
            }
            if (Schema::hasColumn('academic_records', 'credit_points_earned')) {
                $table->renameColumn('credit_points_earned', 'credit_hours_earned');
            }
            if (!Schema::hasColumn('academic_records', 'enrollment_date')) {
                $table->date('enrollment_date')->nullable();
            }
        });

        Schema::table('academic_records', function (Blueprint $table) {
            $table->index(['student_id', 'grade_status', 'completion_status'], 'student_grade_completion_idx');
            $table->index(['program_id', 'semester_id', 'completion_status'], 'program_semester_completion_idx');
            $table->index(['enrollment_date', 'completion_date'], 'enrollment_completion_dates_idx');
            $table->index(['student_id', 'completion_status', 'grade_finalized_date', 'credit_hours'], 'transcript_calc_idx');
            $table->index(['student_id', 'excluded_from_gpa', 'quality_points', 'credit_hours'], 'gpa_calc_idx');
            $table->index(['semester_id', 'unit_id', 'completion_status'], 'semester_unit_completion_idx');

            try {
                DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_credit_hours_earned CHECK (credit_hours_earned >= 0 AND credit_hours_earned <= credit_hours)');
                DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_credit_hours_positive CHECK (credit_hours >= 0)');
            } catch (\Exception $e) {}
        });

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
};
