<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('defer_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_action_log_id')
                ->unique()
                ->constrained('student_action_logs')
                ->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->foreignId('semester_id')->constrained('semesters')->cascadeOnDelete();

            $table->enum('scope_type', ['FULL', 'COURSES']); // Full semester or specific courses
            $table->enum('fee_policy', ['PRESERVE', 'FORFEIT', 'PARTIAL']);
            $table->decimal('preserve_amount', 15, 2)->nullable(); // Amount preserved (null if FORFEIT)

            $table->date('effective_at');
            $table->date('signed_at')->nullable();
            $table->foreignId('upload_record_id')->nullable()->constrained('upload_records')->nullOnDelete();
            $table->foreignId('changed_by_user_id')->constrained('users');
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index('student_id');
            $table->index('semester_id');
            $table->index('scope_type');
            $table->index('fee_policy');
            $table->index(['student_id', 'semester_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('defer_cases');
    }
};
