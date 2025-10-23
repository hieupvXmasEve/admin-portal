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
        // Drop the old constraint that requires credit_hours > 0
        DB::statement('ALTER TABLE academic_records DROP CONSTRAINT check_records_credit_hours_positive');

        // Add new constraint that allows credit_hours >= 0 (including 0 for audit courses)
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_credit_hours_positive CHECK (credit_hours >= 0)');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restore the original constraint that requires credit_hours > 0
        DB::statement('ALTER TABLE academic_records DROP CONSTRAINT check_records_credit_hours_positive');
        DB::statement('ALTER TABLE academic_records ADD CONSTRAINT check_records_credit_hours_positive CHECK (credit_hours > 0)');
    }
};
