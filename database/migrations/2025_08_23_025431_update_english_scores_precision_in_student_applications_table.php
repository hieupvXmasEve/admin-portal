<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Update English test score columns to support both IELTS (0-9.0) and TOEFL (0-120)
     */
    public function up(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            // Change from decimal(4,2) to decimal(5,2) to support TOEFL scores up to 120
            $table->decimal('listening', 5, 2)->nullable()->change();
            $table->decimal('reading', 5, 2)->nullable()->change();
            $table->decimal('writing', 5, 2)->nullable()->change();
            $table->decimal('speaking', 5, 2)->nullable()->change();
            $table->decimal('overall', 5, 2)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            // Revert back to decimal(4,2)
            $table->decimal('listening', 4, 2)->nullable()->change();
            $table->decimal('reading', 4, 2)->nullable()->change();
            $table->decimal('writing', 4, 2)->nullable()->change();
            $table->decimal('speaking', 4, 2)->nullable()->change();
            $table->decimal('overall', 4, 2)->nullable()->change();
        });
    }
};
