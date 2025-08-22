<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * This rollback removes student_code column that was added in previous migrations
     */
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            // Remove unique constraint and drop student_code column
            if (Schema::hasColumn('student_applications', 'student_code')) {
                // Check if unique constraint exists and drop it
                try {
                    $table->dropUnique(['student_code']);
                } catch (Exception $e) {
                    // Constraint might not exist, continue
                }
                
                // Check if index exists and drop it
                try {
                    $table->dropIndex(['student_code']);
                } catch (Exception $e) {
                    // Index might not exist, continue
                }
                
                $table->dropColumn('student_code');
            }
        });
    }

    /**
     * Reverse the migrations.
     * This will re-add the student_code column as it was in the final state
     */
    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            $table->string('student_code', 50)->unique()->after('student_id');
        });
    }
};
