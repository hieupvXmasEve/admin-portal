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
            $table->enum('course_status', ['not_started', 'in_progress', 'completed', 'cancelled'])
                ->default('in_progress')
                ->after('enrollment_status')
                ->comment('Current status of the course: not_started, in_progress, completed, cancelled');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropColumn('course_status');
        });
    }
};
