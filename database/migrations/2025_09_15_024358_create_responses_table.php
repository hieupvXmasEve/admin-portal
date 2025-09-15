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
        Schema::create('responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained()->cascadeOnDelete();
            $table->foreignId('form_version_id')->constrained()->cascadeOnDelete();
            $table->foreignId('campus_id')->nullable()->constrained()->cascadeOnDelete();
            $table->enum('target_scope_type', ['section', 'class_session', 'course', 'global']);
            $table->unsignedBigInteger('target_scope_id')->nullable();
            
            // For student submissions - reference to students table
            $table->unsignedBigInteger('submitted_by_student_id')->nullable();
            $table->foreign('submitted_by_student_id')->references('id')->on('students')->nullOnDelete();
            
            $table->boolean('anonymized')->default(false);
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('submitted');
            
            // Review fields - reference to users table (staff/admin)
            $table->foreignId('reviewed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            
            $table->enum('origin', ['web', 'mobile', 'api'])->default('web');
            $table->dateTime('submitted_at');
            $table->timestamps();
            $table->softDeletes();
            
            $table->index(['form_id', 'campus_id', 'submitted_at'], 'idx_responses_lookup');
            $table->index(['status', 'reviewed_by_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('responses');
    }
};
