<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Queries\ListExamResitEligibleStudentsQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $this->semester->id,
    ]);
});

function failedGradeRecord(Student $student, Campus $campus, Semester $semester, array $overrides = []): AcademicRecord
{
    $unit = $overrides['unit'] ?? Unit::factory()->create();
    unset($overrides['unit']);

    $offering = CourseOffering::factory()->create([
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'campus_id' => $campus->id,
    ]);

    return AcademicRecord::factory()->create(array_merge([
        'student_id' => $student->id,
        'campus_id' => $campus->id,
        'semester_id' => $semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $offering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'override_pass' => false,
        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
        'total_not_recorded' => 0,
    ], $overrides));
}

function eligibleExamResit(array $filters = []): Collection
{
    return app(ListExamResitEligibleStudentsQuery::class)->handle(array_merge([
        'campus_id' => test()->campus->id,
    ], $filters));
}

it('lists an intake_course student with a grade-failed final record', function () {
    $record = failedGradeRecord($this->student, $this->campus, $this->semester);

    $results = eligibleExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['student']->id)->toBe($this->student->id)
        ->and($results->first()['failed_record']->id)->toBe($record->id)
        ->and($results->first()['unit']->id)->toBe($record->unit_id);
});

it('includes an attendance-failed record (staff decides eligibility, not the query)', function () {
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, [
        'failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED,
    ]);

    $results = eligibleExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($record->id);
});

it('includes a both-failed record', function () {
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, [
        'failure_reason' => AcademicRecord::FAILURE_BOTH_FAILED,
    ]);

    $results = eligibleExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($record->id);
});

it('excludes a record that already has an in-flight exam-resit attempt', function () {
    $unit = Unit::factory()->create();
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, ['unit' => $unit]);

    ExamResitAttempt::create([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'unit_id' => $unit->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'request_origin' => ExamResitAttempt::REQUEST_ORIGIN_STAFF,
        'status' => ExamResitAttempt::STATUS_APPROVED,
        'request_sequence' => 1,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
    ]);

    expect(eligibleExamResit())->toHaveCount(0);
});

it('excludes a record whose exam-resit cancellation is still pending finance', function () {
    $unit = Unit::factory()->create();
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, ['unit' => $unit]);

    ExamResitAttempt::create([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'unit_id' => $unit->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'request_origin' => ExamResitAttempt::REQUEST_ORIGIN_STAFF,
        'status' => ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION,
        'request_sequence' => 1,
        'hq_fee_status' => ExamResitAttempt::HQ_FEE_PENDING,
    ]);

    expect(eligibleExamResit())->toHaveCount(0);
});

it('excludes a record that already has an active course-retake registration', function () {
    // cross-lane guard: a record actively being handled in the retake lane must
    // not also appear in the exam-resit lane.
    $unit = Unit::factory()->create();
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
        'failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED,
    ]);

    CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $record->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'status' => CourseRetakeRegistration::STATUS_APPROVED,
        'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
        'attempt_number' => 1,
        'retake_fee' => 500000,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_PENDING,
    ]);

    expect(eligibleExamResit())->toHaveCount(0);
});

it('excludes a record whose course-retake is already enrolled', function () {
    $unit = Unit::factory()->create();
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
    ]);

    CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $record->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'status' => CourseRetakeRegistration::STATUS_ENROLLED,
        'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
        'attempt_number' => 1,
        'retake_fee' => 500000,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_PAID,
        'enrolled_at' => now(),
    ]);

    expect(eligibleExamResit())->toHaveCount(0);
});

it('still lists a later failed record of the same unit when only the earlier record has a retake', function () {
    $unit = Unit::factory()->create();
    $first = failedGradeRecord($this->student, $this->campus, $this->semester, ['unit' => $unit]);
    $second = failedGradeRecord($this->student, $this->campus, $this->semester, ['unit' => $unit]);

    CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $first->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'status' => CourseRetakeRegistration::STATUS_ENROLLED,
        'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
        'attempt_number' => 1,
        'retake_fee' => 500000,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_PAID,
        'enrolled_at' => now(),
    ]);

    $results = eligibleExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($second->id);
});

it('returns a cancelled retake source to the exam-resit eligibility list', function () {
    $unit = Unit::factory()->create();
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
    ]);

    CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $record->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'original_semester_id' => $this->semester->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'status' => CourseRetakeRegistration::STATUS_CANCELLED,
        'request_origin' => CourseRetakeRegistration::REQUEST_ORIGIN_STAFF,
        'attempt_number' => 1,
        'retake_fee' => 500000,
        'hq_fee_status' => CourseRetakeRegistration::HQ_FEE_CANCELLED,
        'cancelled_at' => now(),
    ]);

    expect(eligibleExamResit())->toHaveCount(1);
});

it('excludes a record whose unit the student already passed', function () {
    $unit = Unit::factory()->create();
    failedGradeRecord($this->student, $this->campus, $this->semester, ['unit' => $unit]);
    // A later passing record for the same unit
    failedGradeRecord($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
        'completion_status' => 'completed',
        'is_passed' => true,
        'failure_reason' => null,
    ]);

    expect(eligibleExamResit())->toHaveCount(0);
});

it('includes a record with not-recorded attendance evidence', function () {
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, [
        'total_not_recorded' => 2,
    ]);

    $results = eligibleExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($record->id);
});

it('includes a manual_failed record', function () {
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, [
        'failure_reason' => AcademicRecord::FAILURE_MANUAL_FAILED,
    ]);

    $results = eligibleExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($record->id);
});

it('includes a legacy finalized failed record with no failure_reason (never backfilled) — must match the submit gate', function () {
    $record = failedGradeRecord($this->student, $this->campus, $this->semester, [
        'failure_reason' => null,
    ]);

    $results = eligibleExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($record->id);
});

it('scopes to the given semester_id, excluding a fail record from a different semester', function () {
    $otherSemester = Semester::factory()->create();
    $record = failedGradeRecord($this->student, $this->campus, $this->semester);
    failedGradeRecord($this->student, $this->campus, $otherSemester);

    $results = eligibleExamResit(['semester_id' => $this->semester->id]);

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($record->id);
});

it('returns fail records from every semester when semester_id is not given', function () {
    $otherSemester = Semester::factory()->create();
    failedGradeRecord($this->student, $this->campus, $this->semester);
    failedGradeRecord($this->student, $this->campus, $otherSemester);

    expect(eligibleExamResit())->toHaveCount(2);
});
