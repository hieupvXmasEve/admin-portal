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
            $table->enum('grading_type', ['grade', 'pass_fail'])
                ->default('grade')
                ->after('syllabus_template_id')
                ->comment('Grade: counts toward module average; Pass/Fail: only affects status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropColumn('grading_type');
        });
    }
};
