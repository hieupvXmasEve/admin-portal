<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_action_logs', function (Blueprint $table) {
            if (! Schema::hasColumn('student_action_logs', 'egc_defer_from_block_number')) {
                $table->unsignedTinyInteger('egc_defer_from_block_number')
                    ->nullable()
                    ->after('return_semester_id');
                $table->index('egc_defer_from_block_number', 'student_action_logs_egc_defer_block_idx');
            }
        });

        DB::table('student_action_logs')
            ->where('action_type', 'ACADEMIC_DEFER')
            ->where('previous_status', 'intake_pre_uni_gc')
            ->whereNull('egc_defer_from_block_number')
            ->update(['egc_defer_from_block_number' => 1]);
    }

    public function down(): void
    {
        Schema::table('student_action_logs', function (Blueprint $table) {
            if (Schema::hasColumn('student_action_logs', 'egc_defer_from_block_number')) {
                $table->dropIndex('student_action_logs_egc_defer_block_idx');
                $table->dropColumn('egc_defer_from_block_number');
            }
        });
    }
};
