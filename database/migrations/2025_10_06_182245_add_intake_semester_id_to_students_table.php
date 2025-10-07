<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Get the current active semester ID
            $activeSemesterId = \DB::table('semesters')
                ->where('is_active', true)
                ->value('id');

            // Add intake_semester_id as a required foreign key with active semester as default
            $table->foreignId('intake_semester_id')
                ->default($activeSemesterId ?? 1)
                ->after('curriculum_version_id')
                ->constrained('semesters')
                ->restrictOnDelete();

            // Add intake_mode enum to capture intake sequencing mode
            $table->enum('intake_mode', ['sequential', 'parallel'])
                ->default('sequential')
                ->after('intake_semester_id');

            // Explicit index for query performance
            $table->index('intake_semester_id');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['intake_semester_id']);
            $table->dropIndex(['intake_semester_id']);
            $table->dropColumn('intake_mode');
            $table->dropColumn('intake_semester_id');
        });
    }
};
