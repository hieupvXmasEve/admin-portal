<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the new 'pending_course_opening' status (Chờ mở môn).
     * This status is used when a student is temporarily paused because
     * no suitable course/class section is available yet (typically after
     * pre-uni/EGC or during main course stage).
     */
    public function up(): void
    {
        // We use raw DB statement because modifying ENUM columns with
        // Schema::table()->enum()->change() is unreliable on MariaDB/MySQL
        // when the column already has data and a default.
        DB::statement("
            ALTER TABLE students 
            MODIFY COLUMN status 
            ENUM(
                'active',
                'inactive',
                'suspended',
                'graduated',
                'intake_pre_uni_gc',
                'intake_course',
                'intake_major',
                'deferred',
                'dropout',
                'dropout_transfer',
                'pending',
                'admission_deferred',
                'pending_course_opening'
            ) 
            DEFAULT 'pending'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert to the previous set of values (without pending_course_opening).
        // Order matches the previous migration (2026_01_18_231052_update_student_intake_fields).
        DB::statement("
            ALTER TABLE students 
            MODIFY COLUMN status 
            ENUM(
                'active',
                'inactive',
                'suspended',
                'graduated',
                'intake_pre_uni_gc',
                'intake_course',
                'intake_major',
                'deferred',
                'dropout',
                'dropout_transfer',
                'pending',
                'admission_deferred'
            ) 
            DEFAULT 'pending'
        ");
    }
};
