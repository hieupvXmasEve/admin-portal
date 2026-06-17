<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\ExamResitAttempt;
use App\Models\FinanceCharge;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\CreateExamResitAttemptAction;
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
        'title' => 'Exam resit policy test syllabus',
        'version' => '1.0',
        'total_hours' => 120,
        'total_sessions' => 30,
        'learning_outcomes' => ['Complete the unit outcomes'],
        'grading_criteria' => [['name' => 'Final Exam', 'weight' => 100]],
        'required_materials' => [],
        'is_default' => true,
        'is_active' => true,
        'created_by' => $this->user->id,
        'exam_resit_fee' => 750000,
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

function examResitRecordFor(string $failureReason): AcademicRecord
{
    return AcademicRecord::factory()->create([
        'student_id' => test()->student->id,
        'campus_id' => test()->campus->id,
        'semester_id' => test()->semester->id,
        'unit_id' => test()->unit->id,
        'course_offering_id' => test()->courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'override_pass' => false,
        'final_percentage' => 48,
        'attendance_percentage' => 95,
        'meets_attendance_requirement' => true,
        'failure_reason' => $failureReason,
        'failure_reason_snapshot' => [
            'final_percentage' => 48,
            'grade_threshold' => 60,
            'attendance_percentage' => 95,
            'attendance_threshold' => 80,
            'attendance_evidence_state' => 'recorded',
            'resolver' => 'test',
        ],
    ]);
}

it('creates an auto-approved exam resit source for a grade failure without academic-owned fee creation', function () {
    $record = examResitRecordFor(AcademicRecord::FAILURE_GRADE_FAILED);

    $attempt = app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'notes' => 'Staff allows thi lai',
    ]);

    expect($attempt)->toBeInstanceOf(ExamResitAttempt::class);
    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_APPROVED);
    expect($attempt->request_origin)->toBe(ExamResitAttempt::REQUEST_ORIGIN_STAFF);
    expect($attempt->request_sequence)->toBe(1);
    expect($attempt->attempt_number)->toBeNull();
    expect($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PENDING);
    expect((float) $attempt->fee_amount)->toBe(750000.0);
    expect($attempt->finance_charge_id)->toBeNull();
    expect($attempt->policy_snapshot['max_attempts'])->toBe(1);
    expect($attempt->policy_snapshot['exam_resit_fee'])->toBe(750000);
    expect($attempt->policy_snapshot['late_payment_grace_days'])->toBe(14);
    expect($attempt->policy_snapshot['allow_unpaid_sitting'])->toBeFalse();

    expect(FinanceCharge::query()
        ->where('source_type', ExamResitAttempt::class)
        ->where('source_id', $attempt->id)
        ->exists())->toBeFalse();
});

it('rejects attendance failures from exam resit', function () {
    $record = examResitRecordFor(AcademicRecord::FAILURE_ATTENDANCE_FAILED);

    app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('rejects combined grade and attendance failures from exam resit', function () {
    $record = examResitRecordFor(AcademicRecord::FAILURE_BOTH_FAILED);

    app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);
