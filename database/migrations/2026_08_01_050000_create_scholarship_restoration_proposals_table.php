<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_restoration_proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('scholarship_semester_adjustment_id')
                ->constrained(table: 'scholarship_semester_adjustments', indexName: 'restoration_proposals_adjustment_fk');
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('campus_id')->constrained('campuses');
            // = adjustment.target_semester_id at proposal time — the semester
            // whose results were evaluated as clean.
            $table->foreignId('evaluated_semester_id')->constrained('semesters');
            // pending_approval|approved|rejected — backend allow-list on the
            // model, intentionally not a DB enum.
            $table->string('status', 30);
            $table->text('reason');
            $table->foreignId('proposed_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->nullable()
                ->constrained(table: 'users', indexName: 'restoration_proposals_approved_by_fk');
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            // Idempotency (one pending_approval/approved row per adjustment) is
            // a service-level check-then-create invariant — MySQL has no
            // partial unique index, same pattern as Phase 2/3's tables.
            $table->index('scholarship_semester_adjustment_id', 'restoration_proposals_adjustment_idx');
            $table->index(['campus_id', 'status'], 'restoration_proposals_campus_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_restoration_proposals');
    }
};
