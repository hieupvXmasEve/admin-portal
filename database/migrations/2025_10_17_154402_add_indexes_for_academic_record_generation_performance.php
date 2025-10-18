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
        // Add indexes for academic_records table
        if (! $this->indexExists('academic_records', 'idx_academic_records_student_course')) {
            Schema::table('academic_records', function (Blueprint $table) {
                // Composite index for student and course offering lookup
                $table->index(['student_id', 'course_offering_id'], 'idx_academic_records_student_course');
                
                // Index for course offering queries
                $table->index('course_offering_id', 'idx_academic_records_course_offering');
            });
        }

        // Add indexes for attendances table
        if (! $this->indexExists('attendances', 'idx_attendances_student_session')) {
            Schema::table('attendances', function (Blueprint $table) {
                // Composite index for student and session lookup
                $table->index(['student_id', 'class_session_id'], 'idx_attendances_student_session');
                
                // Index for status filtering
                $table->index('status', 'idx_attendances_status');
            });
        }

        // Add indexes for class_sessions table
        if (! $this->indexExists('class_sessions', 'idx_class_sessions_course_offering')) {
            Schema::table('class_sessions', function (Blueprint $table) {
                // Index for course offering queries
                $table->index('course_offering_id', 'idx_class_sessions_course_offering');
            });
        }

        // Add indexes for assessment_component_detail_scores table
        if (! $this->indexExists('assessment_component_detail_scores', 'idx_scores_detail_student_course')) {
            Schema::table('assessment_component_detail_scores', function (Blueprint $table) {
                // Composite index for unique constraint and lookups
                $table->index(
                    ['assessment_component_detail_id', 'student_id', 'course_offering_id'], 
                    'idx_scores_detail_student_course'
                );
                
                // Index for student queries
                $table->index('student_id', 'idx_scores_student');
                
                // Index for course offering queries
                $table->index('course_offering_id', 'idx_scores_course_offering');
            });
        }

        // Add indexes for assessment_components table
        if (! $this->indexExists('assessment_components', 'idx_components_syllabus_type')) {
            Schema::table('assessment_components', function (Blueprint $table) {
                // Composite index for syllabus and type lookup
                $table->index(['syllabus_template_id', 'type'], 'idx_components_syllabus_type');
            });
        }

        // Add indexes for course_offerings table
        if (! $this->indexExists('course_offerings', 'idx_course_offerings_syllabus')) {
            Schema::table('course_offerings', function (Blueprint $table) {
                // Index for syllabus template lookup
                $table->index('syllabus_template_id', 'idx_course_offerings_syllabus');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop indexes from academic_records
        Schema::table('academic_records', function (Blueprint $table) {
            $table->dropIndex('idx_academic_records_student_course');
            $table->dropIndex('idx_academic_records_course_offering');
        });

        // Drop indexes from attendances
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex('idx_attendances_student_session');
            $table->dropIndex('idx_attendances_status');
        });

        // Drop indexes from class_sessions
        Schema::table('class_sessions', function (Blueprint $table) {
            $table->dropIndex('idx_class_sessions_course_offering');
        });

        // Drop indexes from assessment_component_detail_scores
        Schema::table('assessment_component_detail_scores', function (Blueprint $table) {
            $table->dropIndex('idx_scores_detail_student_course');
            $table->dropIndex('idx_scores_student');
            $table->dropIndex('idx_scores_course_offering');
        });

        // Drop indexes from assessment_components
        Schema::table('assessment_components', function (Blueprint $table) {
            $table->dropIndex('idx_components_syllabus_type');
        });

        // Drop indexes from course_offerings
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropIndex('idx_course_offerings_syllabus');
        });
    }

    /**
     * Check if index exists
     */
    private function indexExists(string $table, string $indexName): bool
    {
        $indexes = DB::select("SHOW INDEX FROM {$table} WHERE Key_name = ?", [$indexName]);
        return count($indexes) > 0;
    }
};
