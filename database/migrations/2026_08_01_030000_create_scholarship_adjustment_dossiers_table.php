<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_adjustment_dossiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            // Snapshot from students.campus_id — policy checks compare against
            // the actor's permission AT this campus, not their session campus.
            $table->foreignId('campus_id')->constrained('campuses');
            $table->foreignId('source_semester_id')->constrained('semesters');
            $table->foreignId('target_semester_id')->constrained('semesters');
            $table->string('status', 40);
            $table->string('source', 20); // system|manual
            $table->text('manual_exception_reason')->nullable();
            $table->json('failed_courses_snapshot');
            $table->string('original_scholarship_code', 50);
            $table->string('original_type', 20); // percentage|fixed_amount
            $table->decimal('original_amount', 12, 2);
            // True when any source record was finalized before the is_passed
            // gate fix (2026-07-04) — routes to mandatory manual verification,
            // honoring "no recalc of old is_passed".
            $table->boolean('needs_data_review')->default(false);

            // Interview (single embedded record; edits are audited via model history).
            $table->string('interview_status', 30)->default('not_scheduled');
            $table->dateTime('interview_scheduled_at')->nullable();
            $table->string('interview_mode', 20)->nullable();
            $table->string('interview_location')->nullable();
            $table->foreignId('interview_staff_id')->nullable()->constrained('users')
                ->nullOnDelete();
            $table->json('interview_participants')->nullable();
            $table->text('interview_agenda')->nullable();
            $table->text('minutes')->nullable();
            $table->unsignedInteger('minutes_version')->default(0);

            // Decision.
            $table->string('decision_type', 30)->nullable(); // keep|reduce|suspend_full|defer|cancel
            $table->decimal('decision_adjusted_amount', 12, 2)->nullable();
            $table->text('decision_reason')->nullable();
            $table->decimal('decision_estimated_impact', 12, 2)->nullable();
            $table->foreignId('proposed_by_user_id')->nullable()->constrained('users')
                ->nullOnDelete();
            $table->foreignId('approved_by_user_id')->nullable()
                ->constrained(table: 'users', indexName: 'sa_dossiers_approved_by_fk')
                ->nullOnDelete();
            $table->dateTime('decided_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->timestamps();

            $table->index(['student_id', 'source_semester_id', 'target_semester_id'], 'sa_dossiers_student_pair_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_adjustment_dossiers');
    }
};
