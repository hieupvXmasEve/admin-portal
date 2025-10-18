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
        Schema::table('academic_records', function (Blueprint $table) {
            // Change credit_hours from decimal(4,2) to decimal(5,2) to allow up to 999.99
            $table->decimal('credit_hours', 5, 2)->change();
            $table->decimal('credit_hours_earned', 5, 2)->default(0.00)->change();

            // Add detailed attendance tracking fields
            $table->integer('total_present')->default(0)->after('total_absences');
            $table->integer('total_late')->default(0)->after('total_present');
            $table->integer('total_not_recorded')->default(0)->after('total_late')->comment('Sessions attended but not yet marked by instructor');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('academic_records', function (Blueprint $table) {
            // Revert back to decimal(4,2)
            $table->decimal('credit_hours', 4, 2)->change();
            $table->decimal('credit_hours_earned', 4, 2)->default(0.00)->change();

            // Drop attendance tracking fields
            $table->dropColumn(['total_present', 'total_late', 'total_not_recorded']);
        });
    }
};
