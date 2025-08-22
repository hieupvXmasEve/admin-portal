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
        Schema::table('student_applications', function (Blueprint $table) {
            // Drop existing index first
            $table->dropIndex(['student_code']);
            
            // Make student_code required and unique
            $table->string('student_code', 50)->nullable(false)->unique()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_applications', function (Blueprint $table) {
            // Remove unique constraint and make nullable again
            $table->dropUnique(['student_code']);
            $table->string('student_code', 50)->nullable()->change();
            
            // Re-add the original index
            $table->index('student_code');
        });
    }
};
