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
        Schema::table('syllabus_templates', function (Blueprint $table) {
            $table->decimal('min_attendance_threshold', 5, 2)->default(80.00)->after('total_sessions');
            $table->decimal('min_grade_threshold', 5, 2)->default(60.00)->after('min_attendance_threshold');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('syllabus_templates', function (Blueprint $table) {
            $table->dropColumn(['min_attendance_threshold', 'min_grade_threshold']);
        });
    }
};
