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
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('Template name for identification');
            $table->enum('type', [
                'welcome',
                'grade_notification',
                'course_registration',
                'academic_hold',
                'enrollment_confirmation',
                'assessment_deadline',
                'system_announcement',
                'reminder',
                'custom'
            ])->comment('Template type for categorization');
            $table->string('subject')->comment('Email subject line template');
            $table->longText('html_content')->comment('HTML email content with variables');
            $table->longText('text_content')->nullable()->comment('Plain text email content');
            $table->json('variables')->nullable()->comment('Available template variables');
            $table->boolean('is_active')->default(true)->comment('Whether template is active');
            $table->integer('version')->default(1)->comment('Template version number');
            $table->foreignId('parent_id')->nullable()->constrained('email_templates')->onDelete('set null');
            $table->text('description')->nullable()->comment('Template description');
            $table->timestamps();

            // Indexes
            $table->index(['type', 'is_active']);
            $table->index('is_active');
            $table->index('version');
            $table->unique(['name', 'version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
