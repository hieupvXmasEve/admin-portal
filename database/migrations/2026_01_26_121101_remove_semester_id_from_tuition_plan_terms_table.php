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
        Schema::table('tuition_plan_terms', function (Blueprint $table) {
            // Drop foreign key first
            $table->dropForeign(['semester_id']);
            
            // Drop the explicit index
            $table->dropIndex('idx_semester');

            // Then drop the column
            $table->dropColumn('semester_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tuition_plan_terms', function (Blueprint $table) {
            $table->foreignId('semester_id')->nullable()->constrained('semesters')->onDelete('cascade');
            $table->index('semester_id', 'idx_semester');
        });
    }
};
