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

    $this->makeFailedRecord = function (array $overrides = []): AcademicRecord {
        $student = Student::factory()->forCampus($this->campus)->create([
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
        ]);

        return AcademicRecord::factory()->create(array_merge([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'unit_id' => $this->unit->id,
            'course_offering_id' => $this->offering->id,
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

    // Học lại detection #1: a retake enrollment in course_registrations (is_retake)
    // for the same (student, unit) as the failed record.
    $this->linkRetakeEnrollment = function (AcademicRecord $record): void {
        CourseRegistration::create([
            'student_id' => $record->student_id,
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
    };

    // Học lại detection #2: the modern retake source's direct record link.
    $this->linkRetakeRegistration = function (AcademicRecord $record): void {
        CourseRetakeRegistration::create([
            'student_id' => $record->student_id,
            'unit_id' => $this->unit->id,
            'original_academic_record_id' => $record->id,
            'course_offering_id' => $this->offering->id,
            'semester_id' => $this->semester->id,
            'campus_id' => $this->campus->id,
        ]);
    };

    // Thi lại detection: the recorded resit attempt's direct record link.
    $this->linkResitAttempt = function (AcademicRecord $record): void {
        ExamResitAttempt::create([
            'student_id' => $record->student_id,
            'academic_record_id' => $record->id,
            'unit_id' => $this->unit->id,
            'campus_id' => $this->campus->id,
            'operation_semester_id' => $this->semester->id,
            'charge_semester_id' => $this->semester->id,
        ]);
    };

    // Thi lại detection (heuristic): a paid PTL fee for the student.
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
    $record = ($this->makeFailedRecord)();
    ($this->linkRetakeEnrollment)($record);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    $record->refresh();
    expect($record->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED)
        ->and($record->is_passed)->toBeFalse()
        ->and($record->failure_reason_snapshot['source'])->toBe('legacy_grade_only');
});

it('labels a failure reached via the modern course-retake source', function () {
    $record = ($this->makeFailedRecord)();
    ($this->linkRetakeRegistration)($record);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($record->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('labels a failure reached via a recorded exam-resit attempt', function () {
    $record = ($this->makeFailedRecord)();
    ($this->linkResitAttempt)($record);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($record->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('labels a paid resit fee to the student\'s single outstanding failed record (heuristic)', function () {
    $record = ($this->makeFailedRecord)();
    ($this->payResitFee)($record->student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($record->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_GRADE_FAILED);
});

it('does not guess when a paid-resit student has several outstanding failed records', function () {
    $student = Student::factory()->forCampus($this->campus)->create([
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $this->semester->id,
    ]);
    $unitB = Unit::factory()->create();
    $offeringB = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $unitB->id,
        'campus_id' => $this->campus->id,
    ]);

    $recordA = ($this->makeFailedRecord)(['student_id' => $student->id]);
    $recordB = ($this->makeFailedRecord)(['student_id' => $student->id, 'unit_id' => $unitB->id, 'course_offering_id' => $offeringB->id]);
    ($this->payResitFee)($student);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($recordA->refresh()->failure_reason)->toBeNull()
        ->and($recordB->refresh()->failure_reason)->toBeNull();
});

it('leaves unlinked finalized failures untouched (the historical 190 stay as-is)', function () {
    $unlinked = ($this->makeFailedRecord)();

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($unlinked->refresh()->failure_reason)->toBeNull();
});

it('never flips a passing record even if it has a retake enrollment', function () {
    $passing = ($this->makeFailedRecord)(['is_passed' => true]);
    ($this->linkRetakeEnrollment)($passing);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($passing->refresh()->failure_reason)->toBeNull();
});

it('is idempotent and does not overwrite an existing failure_reason', function () {
    $record = ($this->makeFailedRecord)(['failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED]);
    ($this->linkRetakeRegistration)($record);

    $this->artisan('academic:backfill-failure-reason')->assertOk();

    expect($record->refresh()->failure_reason)->toBe(AcademicRecord::FAILURE_ATTENDANCE_FAILED);
});

it('writes nothing in dry-run mode', function () {
    $record = ($this->makeFailedRecord)();
    ($this->linkResitAttempt)($record);

    $this->artisan('academic:backfill-failure-reason', ['--dry-run' => true])->assertOk();

    expect($record->refresh()->failure_reason)->toBeNull();
});
