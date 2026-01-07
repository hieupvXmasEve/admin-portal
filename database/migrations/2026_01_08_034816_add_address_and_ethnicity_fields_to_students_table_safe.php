<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Safe migration for production - adds address and ethnicity fields
 *
 * This migration is designed to be safe for large tables:
 * - NO after() clauses (avoids table rewrite)
 * - All columns nullable initially (fast ALTER)
 * - Defaults set via application logic or separate migration
 *
 * For production deployment:
 * 1. Run this migration (fast, minimal lock)
 * 2. Backfill data if needed via application code
 * 3. Optionally run set_defaults migration during maintenance window
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This adds columns WITHOUT after() to avoid table rewrite on large tables.
     * Columns are added at the end of the table for fastest execution.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Current address fields - all nullable, no after() clause
            $table->string('current_address_line', 255)->nullable()->comment('Số nhà, tên đường');
            $table->string('current_ward', 100)->nullable()->comment('Xã / Phường');
            $table->string('current_province', 100)->nullable()->comment('Tỉnh / Thành phố');
            $table->string('current_country', 30)->nullable();

            // CCCD address fields - all nullable, no after() clause
            $table->string('cccd_address_line', 255)->nullable()->comment('Số nhà, tên đường (CCCD)');
            $table->string('cccd_ward', 100)->nullable();
            $table->string('cccd_province', 100)->nullable();
            $table->string('cccd_country', 100)->nullable();

            // Ethnicity field - nullable initially, will be set to NOT NULL in separate migration if needed
            $table->string('ethnicity', 100)->nullable()->comment('Dân tộc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn([
                'current_address_line',
                'current_ward',
                'current_province',
                'current_country',
                'cccd_address_line',
                'cccd_ward',
                'cccd_province',
                'cccd_country',
                'ethnicity',
            ]);
        });
    }
};
