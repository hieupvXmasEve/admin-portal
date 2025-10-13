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
            // Drop unique constraint that includes curriculum_unit_id
            $table->dropUnique('unique_semester_unit_section');
            
            // Drop foreign key constraint
            $table->dropForeign(['curriculum_unit_id']);
            
            // Make curriculum_unit_id nullable
            $table->foreignId('curriculum_unit_id')->nullable()->change();
            
            // Re-add foreign key constraint
            $table->foreign('curriculum_unit_id')->references('id')->on('curriculum_units')->onDelete('set null');
            
            // Add new unique constraint using unit_id instead
            $table->unique(['semester_id', 'unit_id', 'section_code'], 'unique_semester_unit_section');
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
            
            // Drop foreign key
            $table->dropForeign(['curriculum_unit_id']);
            
            // Make curriculum_unit_id not nullable
            $table->foreignId('curriculum_unit_id')->nullable(false)->change();
            
            // Re-add foreign key constraint
            $table->foreign('curriculum_unit_id')->references('id')->on('curriculum_units')->onDelete('cascade');
            
            // Re-add old unique constraint
            $table->unique(['semester_id', 'curriculum_unit_id', 'section_code'], 'unique_semester_unit_section');
        });
    }
};
