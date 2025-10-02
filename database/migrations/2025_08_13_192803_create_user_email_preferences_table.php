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
        Schema::create('user_email_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
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
            ])->comment('Type of notification');
            $table->boolean('is_enabled')->default(true)->comment('Whether this notification type is enabled');
            $table->enum('frequency', [
                'immediate',
                'daily',
                'weekly',
                'never'
            ])->default('immediate')->comment('Notification frequency');
            $table->timestamp('last_sent_at')->nullable()->comment('Last time this type of notification was sent');
            $table->json('settings')->nullable()->comment('Additional notification settings');
            $table->timestamps();

            // Indexes
            $table->unique(['user_id', 'notification_type']);
            $table->index(['user_id', 'is_enabled']);
            $table->index(['notification_type', 'is_enabled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_email_preferences');
    }
};
