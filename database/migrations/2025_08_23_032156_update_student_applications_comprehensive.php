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
        // Check if student_code column exists, if not create it
        if (!Schema::hasColumn('student_applications', 'student_code')) {
            Schema::table('student_applications', function (Blueprint $table) {
                $table->string('student_code', 50)->nullable()->after('student_id');
            });
        }
        
        // Generate unique student codes for existing records that don't have them
        $applicationsWithoutCode = DB::table('student_applications')
            ->where(function($query) {
                $query->whereNull('student_code')
                      ->orWhere('student_code', '');
            })
            ->select('id')
            ->get();
            
        foreach ($applicationsWithoutCode as $application) {
            $studentCode = 'ST' . str_pad($application->id, 6, '0', STR_PAD_LEFT);
            DB::table('student_applications')
                ->where('id', $application->id)
                ->update(['student_code' => $studentCode]);
        }
        
        Schema::table('student_applications', function (Blueprint $table) {
            // Make student_code required and unique if not already
            $table->string('student_code', 50)->nullable(false)->unique()->change();
            
            // Make national_id nullable while keeping unique constraint
            if (Schema::hasColumn('student_applications', 'national_id')) {
                // Drop existing unique constraint if it exists
                try {
                    $table->dropUnique(['national_id']);
                } catch (Exception $e) {
                    // Constraint might not exist, continue
                }
                
                // Make national_id nullable and add back unique constraint
                $table->string('national_id', 20)->nullable()->change();
                $table->unique('national_id');
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
                try {
                    $table->dropUnique(['student_code']);
                } catch (Exception $e) {
                    // Constraint might not exist
                }
                $table->dropColumn('student_code');
            }
            
            // Make national_id required again
            if (Schema::hasColumn('student_applications', 'national_id')) {
                try {
                    $table->dropUnique(['national_id']);
                } catch (Exception $e) {
                    // Constraint might not exist
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
