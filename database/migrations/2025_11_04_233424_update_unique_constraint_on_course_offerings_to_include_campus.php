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
        Schema::table('course_offerings', function (Blueprint $table) {
            // Drop old unique constraint
            $table->dropUnique('unique_semester_unit_section');
            
            // Add new unique constraint including campus_id
            $table->unique(['semester_id', 'unit_id', 'section_code', 'campus_id'], 'unique_semester_unit_section');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            // Drop new unique constraint
            $table->dropUnique('unique_semester_unit_section');
            
            // Restore old unique constraint without campus_id
            $table->unique(['semester_id', 'unit_id', 'section_code'], 'unique_semester_unit_section');
        });
    }
};
