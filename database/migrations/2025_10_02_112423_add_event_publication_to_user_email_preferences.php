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
        Schema::table('user_email_preferences', function (Blueprint $table) {
            $table->enum('notification_type', [
                'welcome',
                'grade_notification',
                'course_registration',
                'academic_hold',
                'enrollment_confirmation',
                'assessment_deadline',
                'system_announcement',
                'reminder',
                'event_publication',
                'all'
            ])->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_email_preferences', function (Blueprint $table) {
            $table->enum('notification_type', [
                'welcome',
                'grade_notification',
                'course_registration',
                'academic_hold',
                'enrollment_confirmation',
                'assessment_deadline',
                'system_announcement',
                'reminder',
                'all'
            ])->change();
        });
    }
};
