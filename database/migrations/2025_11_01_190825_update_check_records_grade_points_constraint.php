<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Skip constraints for SQLite (used in testing)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Drop existing constraint
        try {
            DB::statement('ALTER TABLE academic_records DROP CONSTRAINT check_records_grade_points');
        } catch (\Exception $e) {
            // Constraint might not exist, continue
        }

        // Add updated constraint with new range (0-5)
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_grade_points CHECK (grade_points IS NULL OR (grade_points >= 0 AND grade_points <= 5))');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Skip constraints for SQLite (used in testing)
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        // Drop updated constraint
        try {
            DB::statement('ALTER TABLE academic_records DROP CONSTRAINT check_records_grade_points');
        } catch (\Exception $e) {
            // Constraint might not exist, continue
        }

        // Restore original constraint (0-4)
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_grade_points CHECK (grade_points IS NULL OR (grade_points >= 0 AND grade_points <= 4))');
    }
};
