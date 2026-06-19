<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->unit = Unit::factory()->create();
    $this->offering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
    ]);

    $this->newStudent = fn (): Student => Student::factory()->forCampus($this->campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);

    // A failed (grade-final, is_passed=false, unlabelled) record on a given unit/offering.
    $this->failedRecord = function (Student $student, Unit $unit, CourseOffering $offering, array $overrides = []): AcademicRecord {
        return AcademicRecord::factory()->create(array_merge([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'unit_id' => $unit->id,
            'course_offering_id' => $offering->id,
            'program_id' => $student->program_id,
            'campus_id' => $this->campus->id,
            'credit_hours' => 3.00,
            'credit_points' => 3.00,
            'grade_status' => 'final',
            'is_passed' => false,
            'failure_reason' => null,
            'final_percentage' => 42.0,
        ], $overrides));
    };

    // A failed record on a freshly created unit with the given code.
    $this->failedRecordOnUnit = function (Student $student, string $unitCode): AcademicRecord {
        $unit = Unit::factory()->create(['code' => $unitCode]);
        $offering = CourseOffering::factory()->create([
            'semester_id' => $this->semester->id,
            'unit_id' => $unit->id,
            'campus_id' => $this->campus->id,
        ]);

        return ($this->failedRecord)($student, $unit, $offering);
    };

    $this->payResitFee = function (Student $student): void {
        DngPaymentRequest::query()->create([
            'student_id' => $student->id,
            'campus_code' => 'CMP',
            'student_code' => $student->student_id,
            'fee_type' => 'PTL',
            'item_id' => 'ITEM-'.uniqid(),
            'amount' => 750000,
            'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
        ]);
    };
});

it('labels a failure reached via a course_registrations retake enrollment', function () {
    $student = ($this->newStudent)();
    $record = ($this->failedRecord)($student, $this->unit, $this->offering);
    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $this->offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
        'retake_fee' => 0.00,
        'is_retake_paid' => 'no',
    ]);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($record->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($record->is_passed)->toBeFalse();
});

it('labels a failure reached via the modern course-retake source', function () {
    $student = ($this->newStudent)();
    $record = ($this->failedRecord)($student, $this->unit, $this->offering);
    CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $this->unit->id,
        'original_academic_record_id' => $record->id,
        'course_offering_id' => $this->offering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($record->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('labels a failure reached via a recorded exam-resit attempt', function () {
    $student = ($this->newStudent)();
    $record = ($this->failedRecord)($student, $this->unit, $this->offering);
    ExamResitAttempt::create([
        'student_id' => $student->id,
        'academic_record_id' => $record->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
    ]);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($record->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('labels a paid-PTL student\'s failed resit-unit records but not their other failures', function () {
    $student = ($this->newStudent)();
    $tec = ($this->failedRecordOnUnit)($student, 'TEC001');
    $other = ($this->failedRecordOnUnit)($student, 'AU007');
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($tec->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($other->refresh()->failure_reason)->toBeNull();
});

it('defaults to TEC002 when a paid-PTL student failed both TEC001 and TEC002', function () {
    $student = ($this->newStudent)();
    $tec1 = ($this->failedRecordOnUnit)($student, 'TEC001');
    $tec2 = ($this->failedRecordOnUnit)($student, 'TEC002');
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    // One fee → one resit unit; default priority labels TEC002 and leaves TEC001 null.
    expect($tec2->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($tec1->refresh()->failure_reason)->toBeNull();
});

it('stays on TEC002 across repeated runs and never falls through to TEC001', function () {
    $student = ($this->newStudent)();
    $tec1 = ($this->failedRecordOnUnit)($student, 'TEC001');
    $tec2 = ($this->failedRecordOnUnit)($student, 'TEC002');
    ($this->payResitFee)($student);

    // Running twice must not promote TEC001 once TEC002 is already labelled.
    $this->artisan('academic:backfill-failure-reason')->assertOk();
    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($tec2->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($tec1->refresh()->failure_reason)->toBeNull();
});

it('labels TEC001 when the paid-PTL student did not fail TEC002', function () {
    $student = ($this->newStudent)();
    $tec1 = ($this->failedRecordOnUnit)($student, 'TEC001');
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($tec1->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('reports but does not label a paid-PTL student with no resit-unit academic record', function () {
    $student = ($this->newStudent)();
    $other = ($this->failedRecordOnUnit)($student, 'AU005');
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($other->refresh()->failure_reason)->toBeNull();
});

it('labels TEC002 when a paid-PTL student passed both resit units after the resit', function () {
    $student = ($this->newStudent)();
    $tec1 = ($this->failedRecordOnUnit)($student, 'TEC001');
    $tec2 = ($this->failedRecordOnUnit)($student, 'TEC002');
    $tec1->update(['is_passed' => true, 'final_percentage' => 73.0]);
    $tec2->update(['is_passed' => true, 'final_percentage' => 74.0]);
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($tec2->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($tec2->is_passed)->toBeTrue()
        ->and($tec1->refresh()->failure_reason)->toBeNull();
});

it('labels TEC001 from the paid PTL description when both resit units were passed', function () {
    $student = ($this->newStudent)();
    $tec1 = ($this->failedRecordOnUnit)($student, 'TEC001');
    $tec2 = ($this->failedRecordOnUnit)($student, 'TEC002');
    $tec1->update(['is_passed' => true, 'final_percentage' => 73.0]);
    $tec2->update(['is_passed' => true, 'final_percentage' => 74.0]);
    DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => 'CMP',
        'student_code' => $student->student_id,
        'fee_type' => 'PTL',
        'item_id' => 'ITEM-'.uniqid(),
        'amount' => 750000,
        'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
        'description' => 'Exam Retake Fee: TEC001',
    ]);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($tec1->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($tec2->refresh()->failure_reason)->toBeNull();
});

it('respects the --resit-units override', function () {
    $student = ($this->newStudent)();
    $custom = ($this->failedRecordOnUnit)($student, 'PHYS101');
    $tec = ($this->failedRecordOnUnit)($student, 'TEC001');
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason', ['--resit-units' => 'PHYS101'])->assertOk();

    expect($custom->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($tec->refresh()->failure_reason)->toBeNull();
});

it('leaves failures of students with no remediation untouched (the historical ~190 stay)', function () {
    $student = ($this->newStudent)();
    $orphan = ($this->failedRecordOnUnit)($student, 'TEC001'); // TEC failure but no PTL payment

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($orphan->refresh()->failure_reason)->toBeNull();
});

it('never flips is_passed when labelling a passed resit-unit record from a paid PTL fee', function () {
    $student = ($this->newStudent)();
    $passing = ($this->failedRecordOnUnit)($student, 'TEC001');
    $passing->update(['is_passed' => true]);
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($passing->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($passing->is_passed)->toBeTrue();
});

it('is idempotent and does not overwrite an existing failure_reason', function () {
    $student = ($this->newStudent)();
    $record = ($this->failedRecordOnUnit)($student, 'TEC001');
    $record->update(['failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED]);
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($record->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_ATTENDANCE_FAILED);
});

it('writes nothing in dry-run mode', function () {
    $student = ($this->newStudent)();
    $record = ($this->failedRecordOnUnit)($student, 'TEC001');
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason', ['--dry-run' => true])->assertOk();

    expect($record->refresh()->failure_reason)->toBeNull();
});

it('--reset clears backfilled labels but never finalization-written reasons', function () {
    $student = ($this->newStudent)();

    // A record this backfill wrote (snapshot.backfilled = true).
    $backfilled = ($this->failedRecordOnUnit)($student, 'TEC001');
    ($this->payResitFee)($student);
    $this->artisan('academic:backfill-failure-reason')->assertOk();
    expect($backfilled->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);

    // A record finalization wrote (no backfilled flag) must survive the reset.
    $finalized = ($this->failedRecordOnUnit)($student, 'AU099');
    $finalized->update([
        'failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED,
        'failure_reason_snapshot' => ['attendance_failed' => true],
    ]);

    $this->artisan('academic:backfill-failure-reason', ['--reset' => true])->assertOk();

    expect($backfilled->refresh()->failure_reason)->toBeNull()
        ->and($finalized->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_ATTENDANCE_FAILED);
});
