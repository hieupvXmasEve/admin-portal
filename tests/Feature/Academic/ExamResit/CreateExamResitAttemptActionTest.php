<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\ExamResitAttempt;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SyllabusTemplate;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Delivery\Actions\CreateExamResitAttemptAction;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => 750_000,
        'currency' => 'VND',
        'rule_version' => 'exam_resit_fee:v1',
        'description' => 'Fixed resit fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

function examResitRecordFor(?string $failureReason): AcademicRecord
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

it('creates an auto-approved exam resit source and materializes its finance obligation through intake', function () {
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
    expect($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED);
    expect((float) $attempt->fee_amount)->toBe(750000.0);
    expect($attempt->policy_snapshot['max_attempts'])->toBe(1);
    expect($attempt->policy_snapshot['late_payment_grace_days'])->toBe(14);
    expect($attempt->policy_snapshot['allow_unpaid_sitting'])->toBeFalse();

    $obligation = FinanceObligation::query()
        ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
        ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
        ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($attempt))
        ->where('obligation_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->firstOrFail();
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->firstOrFail();

    expect($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $obligation->amount)->toBe(750_000.0)
        ->and($obligation->pricing_rule_version)->toBe('exam_resit_fee:v1')
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull()
        ->and($charge->charge_type)->toBe(FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->and(InvoiceLine::query()->where('charge_id', $charge->id)->count())->toBe(1);
});

it('rejects registering a second exam-resit attempt while one is already in flight for the record', function () {
    $record = examResitRecordFor(AcademicRecord::FAILURE_GRADE_FAILED);

    app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect(fn () => app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]))->toThrow(ValidationException::class);

    expect(ExamResitAttempt::where('academic_record_id', $record->id)->count())->toBe(1);
});

it('rejects exam-resit when the academic record already has an enrolled course retake', function () {
    $record = examResitRecordFor(AcademicRecord::FAILURE_GRADE_FAILED);

    CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $this->unit->id,
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

    expect(fn () => app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]))->toThrow(ValidationException::class);

    expect(ExamResitAttempt::query()->count())->toBe(0);
});

it('allows exam-resit on a later failed record of the same unit when only the earlier record has a retake', function () {
    $first = examResitRecordFor(AcademicRecord::FAILURE_GRADE_FAILED);
    $laterOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'campus_id' => $this->campus->id,
        'syllabus_template_id' => $this->syllabus->id,
    ]);
    $second = AcademicRecord::factory()->create([
        'student_id' => $this->student->id,
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $this->unit->id,
        'course_offering_id' => $laterOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'override_pass' => false,
        'final_percentage' => 48,
        'attendance_percentage' => 95,
        'meets_attendance_requirement' => true,
        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
    ]);

    CourseRetakeRegistration::create([
        'student_id' => $this->student->id,
        'unit_id' => $this->unit->id,
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

    $attempt = app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $second->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($attempt)->toBeInstanceOf(ExamResitAttempt::class)
        ->and($attempt->academic_record_id)->toBe($second->id);
});

it('rolls back the exam resit source and propagates unrelated finance intake failures untranslated', function () {
    app()->instance(FinanceIntakeContract::class, new class implements FinanceIntakeContract
    {
        public function request(FinanceIntakeData $intake): FinanceIntakeResult
        {
            throw new RuntimeException('finance intake unavailable');
        }

        public function requestDebit(FinanceIntakeData $intake): FinanceIntakeResult
        {
            throw new RuntimeException('finance intake unavailable');
        }

        public function requestCredit(FinanceIntakeData $intake): FinanceIntakeResult
        {
            throw new RuntimeException('finance intake unavailable');
        }

        public function requestDiscount(FinanceIntakeData $intake): FinanceIntakeResult
        {
            throw new RuntimeException('finance intake unavailable');
        }
    });

    $record = examResitRecordFor(AcademicRecord::FAILURE_GRADE_FAILED);

    expect(fn () => app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]))->toThrow(RuntimeException::class, 'finance intake unavailable');

    expect(ExamResitAttempt::query()->count())->toBe(0)
        ->and(FinanceObligation::query()->count())->toBe(0)
        ->and(FinanceCharge::query()->count())->toBe(0);
});

it('throws a validation error when no catalog pricing rule exists for the unit', function () {
    DB::table('finance_pricing_catalog_items')
        ->where('obligation_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->delete();

    $record = examResitRecordFor(AcademicRecord::FAILURE_GRADE_FAILED);

    try {
        app(CreateExamResitAttemptAction::class)->run([
            'student_id' => $this->student->id,
            'academic_record_id' => $record->id,
            'operation_semester_id' => $this->semester->id,
            'charge_semester_id' => $this->semester->id,
            'campus_id' => $this->campus->id,
        ]);

        $this->fail('Expected ValidationException was not thrown.');
    } catch (ValidationException $exception) {
        expect($exception->errors()['policy'][0])
            ->toBe('Chưa cấu hình giá thi lại cho môn này. Vui lòng cấu hình tại Pricing Operations.');
    }

    expect(ExamResitAttempt::query()->count())->toBe(0)
        ->and(FinanceObligation::query()->count())->toBe(0)
        ->and(FinanceCharge::query()->count())->toBe(0);
});

it('allows registering an attendance-failed record for exam resit (staff decision, no longer routed away)', function () {
    $record = examResitRecordFor(AcademicRecord::FAILURE_ATTENDANCE_FAILED);

    $attempt = app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($attempt)->toBeInstanceOf(ExamResitAttempt::class)
        ->and($attempt->academic_record_id)->toBe($record->id);
});

it('allows registering a combined grade-and-attendance-failed record for exam resit', function () {
    $record = examResitRecordFor(AcademicRecord::FAILURE_BOTH_FAILED);

    $attempt = app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($attempt)->toBeInstanceOf(ExamResitAttempt::class)
        ->and($attempt->academic_record_id)->toBe($record->id);
});

it('allows registering a legacy record with no failure_reason (never backfilled), matching the eligible list', function () {
    $record = examResitRecordFor(null);

    $attempt = app(CreateExamResitAttemptAction::class)->run([
        'student_id' => $this->student->id,
        'academic_record_id' => $record->id,
        'operation_semester_id' => $this->semester->id,
        'charge_semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($attempt)->toBeInstanceOf(ExamResitAttempt::class)
        ->and($attempt->academic_record_id)->toBe($record->id);
});
