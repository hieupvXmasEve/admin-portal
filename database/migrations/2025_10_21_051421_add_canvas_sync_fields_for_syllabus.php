<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Add Canvas sync tracking to course_offerings.
     * Canvas Assignment Groups sync to AssessmentComponents (not to SyllabusTemplate).
     */
    public function up(): void
    {
        // Add Canvas sync flag to course_offerings
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->boolean('is_canvas_synced')->default(false)->after('current_waitlist');
            $table->timestamp('canvas_synced_at')->nullable()->after('is_canvas_synced');

            $table->index('is_canvas_synced');
        });
    }

    public function down(): void
    {
        Schema::table('course_offerings', function (Blueprint $table) {
            $table->dropIndex(['is_canvas_synced']);
            $table->dropColumn(['is_canvas_synced', 'canvas_synced_at']);
        });
    }
};
