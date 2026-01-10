<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add 'admission_deferred' to the status enum
        DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('active', 'inactive', 'suspended', 'graduated', 'intake_pre_uni_gc', 'intake_course', 'deferred', 'dropout', 'dropout_transfer', 'pending', 'admission_deferred') DEFAULT 'pending'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove 'admission_deferred' from the status enum
        DB::statement("ALTER TABLE students MODIFY COLUMN status ENUM('active', 'inactive', 'suspended', 'graduated', 'intake_pre_uni_gc', 'intake_course', 'deferred', 'dropout', 'dropout_transfer', 'pending') DEFAULT 'pending'");
    }
};
