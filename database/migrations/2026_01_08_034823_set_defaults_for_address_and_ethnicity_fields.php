<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Optional migration to set defaults and constraints
 *
 * ⚠️ WARNING: This migration may cause table lock on large tables.
 * Only run this during maintenance window or low-traffic period.
 *
 * This migration:
 * 1. Backfills default values for existing records
 * 2. Sets default values on columns
 * 3. Optionally sets NOT NULL constraint (commented out by default)
 *
 * Steps:
 * 1. Run the safe migration first (add_address_and_ethnicity_fields_to_students_table_safe)
 * 2. Backfill data via application code if needed
 * 3. Run this migration during maintenance window
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Backfill default values for existing records (fast UPDATE)
        DB::statement("UPDATE students SET current_country = 'Vietnam' WHERE current_country IS NULL");
        DB::statement("UPDATE students SET cccd_country = 'Vietnam' WHERE cccd_country IS NULL");
        DB::statement("UPDATE students SET ethnicity = 'kinh' WHERE ethnicity IS NULL");

        // Step 2: Set default values on columns (requires ALTER but no data rewrite)
        Schema::table('students', function (Blueprint $table) {
            $table->string('current_country', 30)->default('Vietnam')->change();
            $table->string('cccd_country', 100)->default('Vietnam')->change();
            // Note: ethnicity default is set but column remains nullable
            // Uncomment below ONLY if you want to enforce NOT NULL constraint
            // This will cause table rewrite on large tables!
            // $table->string('ethnicity', 100)->default('kinh')->nullable(false)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Remove defaults (columns remain nullable)
            $table->string('current_country', 30)->nullable()->change();
            $table->string('cccd_country', 100)->nullable()->change();
            $table->string('ethnicity', 100)->nullable()->change();
        });
    }
};
