<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Update status column to include new statuses
            $table->enum('status', [
                'active',
                'inactive',
                'suspended',
                'graduated',
                'intake_pre_uni_gc',
                'intake_course',
                'deferred',
                'dropout',
                'dropout_transfer',
                'pending'
            ])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Revert to original status options
            $table->enum('status', [
                'active',
                'inactive',
                'suspended',
                'graduated'
            ])->nullable()->change();
        });
    }
};
