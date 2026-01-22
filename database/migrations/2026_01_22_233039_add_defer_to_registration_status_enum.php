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
        // First, convert all 'completed' values to 'confirmed' since we're removing 'completed' from enum
        // This must be done while 'completed' is still a valid enum value
        // DB::statement("UPDATE course_registrations SET registration_status = 'confirmed' WHERE registration_status = 'completed'");

        // Now safely modify the enum to remove 'completed' and add 'defer'
        DB::statement("ALTER TABLE course_registrations MODIFY COLUMN registration_status ENUM('registered', 'confirmed', 'dropped', 'withdrawn', 'completed', 'defer') NOT NULL DEFAULT 'registered'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Convert any 'defer' values back to 'withdrawn' before removing the enum value
        // DB::statement("UPDATE course_registrations SET registration_status = 'withdrawn' WHERE registration_status = 'defer'");

        // Remove 'defer' from enum
        DB::statement("ALTER TABLE course_registrations MODIFY COLUMN registration_status ENUM('registered', 'confirmed', 'dropped', 'withdrawn', 'completed') NOT NULL DEFAULT 'registered'");
    }
};
