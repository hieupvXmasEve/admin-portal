<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Comprehensive update to student_applications table including:
     * 1. Add student_code column (unique, required) - or update existing
     * 2. Make national_id nullable (but still unique)
     * 3. Update English score precision to support TOEFL (0-120) and IELTS (0-9.0)
     */
    public function up(): void
    {
        // Step 1: Add student_code column if it doesn't exist
        if (!Schema::hasColumn('student_applications', 'student_code')) {
            Schema::table('student_applications', function (Blueprint $table) {
                $table->string('student_code', 50)->nullable()->after('status');
            });
        }
        
        // Step 2: Generate unique student codes for existing records that don't have them
        $applicationsWithoutCode = DB::table('student_applications')
            ->where(function($query) {
                $query->whereNull('student_code')
                      ->orWhere('student_code', '');
            })
            ->select('id')
            ->get();
            
        foreach ($applicationsWithoutCode as $application) {
            $studentCode = 'SWU' . str_pad($application->id, 6, '0', STR_PAD_LEFT);
            DB::table('student_applications')
                ->where('id', $application->id)
                ->update(['student_code' => $studentCode]);
        }
        
        // Step 3: Make student_code required and unique, and update other columns
        Schema::table('student_applications', function (Blueprint $table) {
            // Make student_code required and unique if not already
            if (Schema::hasColumn('student_applications', 'student_code')) {
                $table->string('student_code', 50)->nullable(false)->unique()->change();
            }
            
            // Handle national_id - make nullable but keep unique constraint
            if (Schema::hasColumn('student_applications', 'national_id')) {
                // Check if unique constraint exists before dropping
                $uniqueConstraints = DB::select("SHOW INDEX FROM student_applications WHERE Key_name LIKE '%national_id%' AND Non_unique = 0");
                if (!empty($uniqueConstraints)) {
                    try {
                        $table->dropUnique(['national_id']);
                    } catch (Exception $e) {
                        // Constraint might not exist or have different name, continue
                    }
                }
                
                // Make national_id nullable and add back unique constraint
                $table->string('national_id', 20)->nullable()->change();
                $table->unique('national_id', 'student_applications_national_id_unique');
            }
            
            // Update English test score precision to support both IELTS (0-9.0) and TOEFL (0-120)
            if (Schema::hasColumn('student_applications', 'listening')) {
                $table->decimal('listening', 5, 2)->nullable()->change();
            }
            if (Schema::hasColumn('student_applications', 'reading')) {
                $table->decimal('reading', 5, 2)->nullable()->change();
            }
            if (Schema::hasColumn('student_applications', 'writing')) {
                $table->decimal('writing', 5, 2)->nullable()->change();
            }
            if (Schema::hasColumn('student_applications', 'speaking')) {
                $table->decimal('speaking', 5, 2)->nullable()->change();
            }
            if (Schema::hasColumn('student_applications', 'overall')) {
                $table->decimal('overall', 5, 2)->nullable()->change();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            // Remove student_code column
            if (Schema::hasColumn('student_applications', 'student_code')) {
                // Check for unique constraint before dropping
                $uniqueConstraints = DB::select("SHOW INDEX FROM student_applications WHERE Key_name LIKE '%student_code%' AND Non_unique = 0");
                if (!empty($uniqueConstraints)) {
                    try {
                        $table->dropUnique(['student_code']);
                    } catch (Exception $e) {
                        // Constraint might not exist or have different name
                    }
                }
                $table->dropColumn('student_code');
            }
            
            // Make national_id required again
            if (Schema::hasColumn('student_applications', 'national_id')) {
                // Check for unique constraint before dropping
                $uniqueConstraints = DB::select("SHOW INDEX FROM student_applications WHERE Key_name LIKE '%national_id%' AND Non_unique = 0");
                if (!empty($uniqueConstraints)) {
                    try {
                        $table->dropUnique(['national_id']);
                    } catch (Exception $e) {
                        // Constraint might not exist or have different name
                    }
                }
                $table->string('national_id', 20)->nullable(false)->change();
                $table->unique('national_id');
            }
            
            // Revert English score precision back to decimal(4,2)
            if (Schema::hasColumn('student_applications', 'listening')) {
                $table->decimal('listening', 4, 2)->nullable()->change();
            }
            if (Schema::hasColumn('student_applications', 'reading')) {
                $table->decimal('reading', 4, 2)->nullable()->change();
            }
            if (Schema::hasColumn('student_applications', 'writing')) {
                $table->decimal('writing', 4, 2)->nullable()->change();
            }
            if (Schema::hasColumn('student_applications', 'speaking')) {
                $table->decimal('speaking', 4, 2)->nullable()->change();
            }
            if (Schema::hasColumn('student_applications', 'overall')) {
                $table->decimal('overall', 4, 2)->nullable()->change();
            }
        });
    }
};
