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
            $table->foreignId('syllabus_template_id')
                ->nullable()
                ->after('curriculum_unit_id')
                ->constrained('syllabus_templates')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropForeign(['syllabus_template_id']);
            $table->dropColumn('syllabus_template_id');
        });
    }
};
