<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Queries\ListExamResitBlockedStudentsQuery;
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

function blockedFailedRecord(Student $student, Campus $campus, Semester $semester, array $overrides = []): AcademicRecord
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

function blockedExamResit(array $filters = []): Collection
{
    return app(ListExamResitBlockedStudentsQuery::class)->handle(array_merge([
        'campus_id' => test()->campus->id,
    ], $filters));
}

it('returns empty when nothing is blocked', function () {
    blockedFailedRecord($this->student, $this->campus, $this->semester);

    expect(blockedExamResit())->toHaveCount(0);
});

it('flags a record whose unit the student already passed as unit_already_passed', function () {
    $unit = Unit::factory()->create();
    $record = blockedFailedRecord($this->student, $this->campus, $this->semester, ['unit' => $unit]);
    blockedFailedRecord($this->student, $this->campus, $this->semester, [
        'unit' => $unit,
        'completion_status' => 'completed',
        'is_passed' => true,
        'failure_reason' => null,
    ]);

    $results = blockedExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($record->id)
        ->and($results->first()['reason_code'])->toBe(ListExamResitBlockedStudentsQuery::REASON_UNIT_ALREADY_PASSED);
});

it('flags a record with an in-flight exam-resit attempt as resit_already_in_flight', function () {
    $unit = Unit::factory()->create();
    $record = blockedFailedRecord($this->student, $this->campus, $this->semester, ['unit' => $unit]);

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

    $results = blockedExamResit();

    expect($results)->toHaveCount(1)
        ->and($results->first()['failed_record']->id)->toBe($record->id)
        ->and($results->first()['reason_code'])->toBe(ListExamResitBlockedStudentsQuery::REASON_RESIT_ALREADY_IN_FLIGHT);
});
