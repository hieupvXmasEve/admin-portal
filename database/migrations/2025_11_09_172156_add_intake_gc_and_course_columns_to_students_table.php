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
            $table->string('intake_gc')->nullable()->after('intake_mode');
            $table->string('intake_course')->nullable()->after('intake_gc');
            $table->string('gc_to_course_transition_semester')->nullable()->after('intake_course');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['intake_gc', 'intake_course', 'gc_to_course_transition_semester']);
        });
    }
};
