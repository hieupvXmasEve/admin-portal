<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * curriculum_unit_id is redundant: unit_id is the canonical FK on course_offerings.
     * Drop the column, its FK, and the legacy composite index.
     */
    public function up(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            // Drop legacy index (semester_id, curriculum_unit_id) from original migration
            $table->dropIndex(['semester_id', 'curriculum_unit_id']);

            // Drop the nullable FK constraint, then the column
            $table->dropForeign(['curriculum_unit_id']);
            $table->dropColumn('curriculum_unit_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->foreignId('curriculum_unit_id')->nullable()->constrained()->onDelete('set null');
            $table->index(['semester_id', 'curriculum_unit_id']);
        });
    }
};
