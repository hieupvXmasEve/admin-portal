<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_enrollments', function (Blueprint $table): void {
            $table->unsignedTinyInteger('egc_starting_level')->nullable()->after('study_stage');
            $table->unsignedTinyInteger('egc_current_level')->nullable()->after('egc_starting_level');
            $table->unsignedTinyInteger('egc_total_levels')->nullable()->after('egc_current_level');
        });

        DB::statement('
            UPDATE program_enrollments AS enrollment
            INNER JOIN students AS student ON student.id = enrollment.student_id
            SET enrollment.egc_starting_level = student.gc_starting_level,
                enrollment.egc_current_level = student.gc_current_level,
                enrollment.egc_total_levels = student.gc_total_levels
            WHERE enrollment.study_stage = \'intake_pre_uni_gc\'
        ');
    }

    public function down(): void
    {
        Schema::table('program_enrollments', function (Blueprint $table): void {
            $table->dropColumn(['egc_starting_level', 'egc_current_level', 'egc_total_levels']);
        });
    }
};
