<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\CompleteExamResitAttemptAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);
    $this->unit = Unit::factory()->create();
    $this->syllabus = SyllabusTemplate::create([
        'unit_id' => $this->unit->id,
        'title' => 'Exam resit completion test syllabus',
        'version' => '1.0',
        'total_hours' => 120,
        'total_sessions' => 30,
        'min_grade_threshold' => 60,
        'min_attendance_threshold' => 80,
        'learning_outcomes' => ['Complete the unit outcomes'],
        'grading_criteria' => [['name' => 'Final Exam', 'weight' => 100]],
        'required_materials' => [],
        'is_default' => true,
        'is_active' => true,
        'created_by' => $this->user->id,
        'exam_resit_max_attempts' => 1,
        'exam_resit_late_payment_grace_days' => 14,
        'exam_resit_allow_unpaid_sitting' => false,
    ]);
    $this->courseOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
        'syllabus_template_id' => $this->syllabus->id,
    ]);
});

/**
 * Build an approved exam-resit attempt with a controllable failed academic record,
 * ready for the completion (result write-back) action.
 *
 * @param  array<string, mixed>  $attemptOverrides
 * @param  array<string, mixed>  $recordOverrides
 */
function completableExamResitAttempt(
    float $originalScore = 48.0,
    array $attemptOverrides = [],
    array $recordOverrides = [],
): ExamResitAttempt {
    $record = AcademicRecord::factory()->create(array_merge([
        'student_id' => test()->student->id,
        'campus_id' => test()->campus->id,
        'semester_id' => test()->semester->id,
        'unit_id' => test()->unit->id,
        'course_offering_id' => test()->courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'override_pass' => false,
        'final_percentage' => $originalScore,
        'final_letter_grade' => AcademicRecord::calculateLetterGrade($originalScore),
        'grade_points' => AcademicRecord::calculateGradePoints($originalScore),
        'credit_hours' => 3,
        'credit_points' => 3,
        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
    ], $recordOverrides));

    $simulatePaid = ($attemptOverrides['hq_fee_status'] ?? ExamResitAttempt::HQ_FEE_PAID) === ExamResitAttempt::HQ_FEE_PAID;
    unset($attemptOverrides['hq_fee_status'], $attemptOverrides['paid_at']);

    $attempt = ExamResitAttempt::create(array_merge([
        'student_id' => test()->student->id,
        'academic_record_id' => $record->id,
        'original_course_offering_id' => test()->courseOffering->id,
        'unit_id' => test()->unit->id,
        'campus_id' => test()->campus->id,
        'syllabus_template_id' => test()->syllabus->id,
        'original_semester_id' => test()->semester->id,
        'operation_semester_id' => test()->semester->id,
        'charge_semester_id' => test()->semester->id,
        'request_origin' => ExamResitAttempt::REQUEST_ORIGIN_STAFF,
        'status' => ExamResitAttempt::STATUS_SCHEDULED,
        'request_sequence' => 1,
        'attempt_number' => null,
        'approved_at' => now(),
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
        'paid_at' => null,
        'fee_amount' => 750000,
        'exam_resit_fee_snapshot' => 750000,
        'max_attempts_snapshot' => 1,
        'late_payment_grace_days_snapshot' => 14,
        'allow_unpaid_sitting_snapshot' => false,
    ], $attemptOverrides));

    if ($simulatePaid) {
        return settleExamResitAttemptLedger($attempt);
    }

    $attempt->update([
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
    ]);

    return $attempt->fresh() ?? $attempt;
}

it('applies a higher passing resit score to the academic record using the higher-score rule', function () {
    $attempt = completableExamResitAttempt(originalScore: 48.0);
    $record = $attempt->academicRecord;

    $result = app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
    ]);

    $record->refresh();

    expect($result->status)->toBe(ExamResitAttempt::STATUS_COMPLETED)
        ->and($result->attempt_number)->toBe(1)
        ->and((float) $result->resit_score)->toBe(75.0)
        ->and((float) $result->final_chosen_score)->toBe(75.0)
        ->and($result->completed_at)->not->toBeNull();

    // Academic record (hard gate) now reflects the higher resit score and passes.
    expect((float) $record->final_percentage)->toBe(75.0)
        ->and((bool) $record->is_passed)->toBeTrue()
        ->and($record->completion_status)->toBe('completed')
        ->and($record->failure_reason)->toBeNull()
        ->and((float) $record->credit_points_earned)->toBe(3.0)
        ->and($record->final_letter_grade)->toBe(AcademicRecord::calculateLetterGrade(75.0));
});

it('preserves the previous result in attempt history and academic_records.grade_history', function () {
    $attempt = completableExamResitAttempt(originalScore: 48.0);
    $record = $attempt->academicRecord;

    $result = app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
    ]);

    $record->refresh();

    // Attempt keeps the pre-resit snapshot.
    expect((float) $result->previous_result_snapshot['final_percentage'])->toBe(48.0)
        ->and($result->previous_result_snapshot['completion_status'])->toBe('failed')
        ->and((bool) $result->previous_result_snapshot['is_passed'])->toBeFalse();

    // Academic record grade_history accumulates a resit application entry.
    $applications = $record->grade_history['exam_resit_applications'] ?? [];
    expect($applications)->toHaveCount(1)
        ->and($applications[0]['exam_resit_attempt_id'])->toBe($attempt->id)
        ->and((float) $applications[0]['previous_final_percentage'])->toBe(48.0)
        ->and((float) $applications[0]['resit_score'])->toBe(75.0)
        ->and((float) $applications[0]['final_chosen_score'])->toBe(75.0)
        ->and($applications[0]['applied'])->toBeTrue();
});

it('does not lower the academic record when the resit score is lower than the original', function () {
    $attempt = completableExamResitAttempt(originalScore: 48.0);
    $record = $attempt->academicRecord;

    $result = app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 40.0,
    ]);

    $record->refresh();

    // Higher-score rule keeps the original score; attempt is still consumed/recorded.
    expect((float) $record->final_percentage)->toBe(48.0)
        ->and((bool) $record->is_passed)->toBeFalse()
        ->and($record->completion_status)->toBe('failed') // not-applied path: original seed state is untouched
        ->and($result->status)->toBe(ExamResitAttempt::STATUS_COMPLETED)
        ->and($result->attempt_number)->toBe(1)
        ->and((float) $result->resit_score)->toBe(40.0)
        ->and((float) $result->final_chosen_score)->toBe(48.0)
        ->and($result->result_snapshot['applied'])->toBeFalse();
});

it('raises a higher-but-still-failing resit score without flipping pass state', function () {
    $attempt = completableExamResitAttempt(originalScore: 48.0);
    $record = $attempt->academicRecord;

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 55.0,
    ]);

    $record->refresh();

    expect((float) $record->final_percentage)->toBe(55.0)
        ->and((bool) $record->is_passed)->toBeFalse()
        ->and($record->completion_status)->toBe('completed') // 'finished', not 'passed'; fail signal is is_passed=false above
        ->and($record->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('normalizes a still-failing applied resit to grade_failed and clears a manual override', function () {
    $attempt = completableExamResitAttempt(
        originalScore: 48.0,
        recordOverrides: [
            'failure_reason' => AcademicRecord::FAILURE_MANUAL_FAILED,
            'override_pass' => true,
        ],
    );
    $record = $attempt->academicRecord;

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 55.0,
    ]);

    $record->refresh();

    // A recorded resit sitting supersedes the prior manual override: the still-failing
    // result is, by definition, a grade failure (attendance was fine to be eligible).
    expect((float) $record->final_percentage)->toBe(55.0)
        ->and((bool) $record->is_passed)->toBeFalse()
        ->and($record->completion_status)->toBe('completed') // 'finished', not 'passed'; fail signal is is_passed=false above
        ->and($record->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and((bool) $record->override_pass)->toBeFalse();
});

it('rejects completion when the fee is unpaid and policy forbids unpaid sitting', function () {
    $attempt = completableExamResitAttempt(attemptOverrides: [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'paid_at' => null,
        'allow_unpaid_sitting_snapshot' => false,
    ]);

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
    ]);
})->throws(ValidationException::class);

it('allows unpaid sitting with a recorded reason when policy permits it', function () {
    $attempt = completableExamResitAttempt(attemptOverrides: [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'paid_at' => null,
        'allow_unpaid_sitting_snapshot' => true,
    ]);

    $result = app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
        'unpaid_sitting_reason' => 'Academic approved sitting before payment',
    ]);

    expect($result->status)->toBe(ExamResitAttempt::STATUS_COMPLETED)
        ->and($result->unpaid_allowed_reason)->toBe('Academic approved sitting before payment')
        ->and($result->unpaid_allowed_by_user_id)->toBe($this->user->id)
        ->and($result->unpaid_allowed_at)->not->toBeNull();
});

it('requires a reason when allowing unpaid sitting', function () {
    $attempt = completableExamResitAttempt(attemptOverrides: [
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_CHARGE_CREATED,
        'paid_at' => null,
        'allow_unpaid_sitting_snapshot' => true,
    ]);

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
    ]);
})->throws(ValidationException::class);

it('cannot complete an attempt that is already completed', function () {
    $attempt = completableExamResitAttempt();

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
    ]);

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 80.0,
    ]);
})->throws(ValidationException::class);

it('rejects completing an attempt that has not been scheduled yet', function () {
    // Now that scheduling infra exists, completion must require `scheduled`:
    // an approved-but-unscheduled attempt cannot be completed.
    $attempt = completableExamResitAttempt(attemptOverrides: [
        'status' => ExamResitAttempt::STATUS_APPROVED,
    ]);

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
    ]);
})->throws(ValidationException::class);

it('rejects an out-of-range resit score', function () {
    $attempt = completableExamResitAttempt();

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 140.0,
    ]);
})->throws(ValidationException::class);

it('preserves resit grade_history and attempt audit after a later score overwrite', function () {
    $attempt = completableExamResitAttempt(originalScore: 48.0);
    $record = $attempt->academicRecord;

    app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
    ]);

    // Simulate a later Canvas sync / re-finalization overwriting the final score.
    $record->refresh();
    $record->update(['final_percentage' => 30, 'grade_status' => 'final']);

    $record->refresh();
    $attempt->refresh();

    // The resit application audit must survive the later overwrite.
    $applications = $record->grade_history['exam_resit_applications'] ?? [];
    expect($applications)->toHaveCount(1)
        ->and($attempt->status)->toBe(ExamResitAttempt::STATUS_COMPLETED)
        ->and((float) $attempt->resit_score)->toBe(75.0)
        ->and((float) $attempt->final_chosen_score)->toBe(75.0)
        ->and($attempt->result_snapshot)->not->toBeNull();
});

it('flags GPA recalculation as required when an applied resit flips pass state', function () {
    $attempt = completableExamResitAttempt(originalScore: 48.0);

    $result = app(CompleteExamResitAttemptAction::class)->run([
        'attempt_id' => $attempt->id,
        'resit_score' => 75.0,
    ]);

    expect($result->result_snapshot['requires_gpa_recalc'])->toBeTrue();
});
