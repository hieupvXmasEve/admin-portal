<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scholarship_semester_adjustments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            // Snapshot from students.campus_id at approval time — required for
            // record-level campus permission checks on every later decision.
            $table->foreignId('campus_id')->constrained('campuses');
            // Explicit short index name — the auto-generated one exceeds
            // MariaDB's 64-char identifier limit.
            $table->foreignId('student_scholarship_award_id')
                ->constrained(table: 'student_scholarship_awards', indexName: 'ssa_adjustments_award_id_fk');
            $table->string('scholarship_code', 50);
            $table->foreignId('source_semester_id')->constrained('semesters');
            $table->foreignId('target_semester_id')->constrained('semesters');
            // Snapshot of the award terms at approval: percentage|fixed_amount
            // (backend allow-list, intentionally not a DB enum).
            $table->string('original_type', 20);
            $table->decimal('original_amount', 12, 2);
            // hash(code|type|amount) at approval — re-validated at apply time so a
            // mutated award (e.g. financial import) can never be silently applied.
            $table->string('award_fingerprint', 64);
            // 0 = full suspension. Always within [0, original_amount].
            $table->decimal('adjusted_amount', 12, 2);
            // pending_apply|applied|finance_review_required|reversed — allow-list
            // lives on the model; status is never overloaded for restoration.
            $table->string('status', 40);
            $table->text('reason');
            // Academic dossier reference, stored opaque (read back through the
            // Academic contract — deliberately no FK across the module boundary).
            $table->unsignedBigInteger('academic_dossier_id');
            $table->foreignId('created_by_user_id')->constrained('users');
            $table->foreignId('approved_by_user_id')->constrained('users');
            $table->foreignId('reversed_by_user_id')->nullable()
                ->constrained(table: 'users', indexName: 'ssa_adjustments_reversed_by_fk');
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            // Phase 3 reads adjustments back by dossier.
            $table->index('academic_dossier_id', 'ssa_adjustments_dossier_idx');

            // Active-uniqueness (max one non-reversed row per student+target
            // semester) is enforced service-level with lockForUpdate — MySQL has
            // no partial unique indexes.
            $table->index(['student_id', 'target_semester_id'], 'ssa_adjustments_student_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scholarship_semester_adjustments');
    }
};
