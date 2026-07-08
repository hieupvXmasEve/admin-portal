<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\CurriculumUnit;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\UnitPrerequisiteCondition;
use App\Models\UnitPrerequisiteGroup;
use App\Models\User;
use App\Modules\Academic\Actions\CancelRetakeCourseRegistrationAction;
use App\Modules\Academic\Actions\CreateRetakeCourseRegistrationAction;
use App\Modules\Academic\Queries\ListRetakeCourseEligibleStudentsQuery;
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Models\FinanceObligation;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function () {
    Cache::flush();

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

    $this->courseOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'enrollment_status' => 'open',
        'course_status' => 'not_started',
    ]);

    CurriculumUnit::factory()->create([
        'curriculum_version_id' => $this->student->curriculum_version_id,
        'unit_id' => $this->courseOffering->unit_id,
        'semester_id' => $this->semester->id,
    ]);

    $this->academicRecord = AcademicRecord::factory()->create([
        'student_id' => $this->student->id,
        'campus_id' => $this->campus->id,
        'unit_id' => $this->courseOffering->unit_id,
        'course_offering_id' => $this->courseOffering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    DB::table('finance_pricing_catalog_items')->insert([
        'obligation_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 1_500_000,
        'currency' => 'VND',
        'rule_version' => 'retake_fee:v1',
        'description' => 'Fixed retake fee',
        'is_active' => true,
        'effective_from' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
});

it('creates an auto-approved retake source and materializes its finance obligation through intake', function () {
    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result)->toBeInstanceOf(CourseRetakeRegistration::class);
    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($result->student_id)->toBe($this->student->id);
    expect($result->unit_id)->toBe($this->courseOffering->unit_id);
    expect($result->request_origin)->toBe(CourseRetakeRegistration::REQUEST_ORIGIN_STAFF);
    expect($result->hq_fee_status)->toBe(CourseRetakeRegistration::HQ_FEE_CHARGE_CREATED);
    expect($result->original_semester_id)->toBe($this->academicRecord->semester_id);
    expect($result->operation_semester_id)->toBe($this->semester->id);
    expect($result->charge_semester_id)->toBe($this->semester->id);
    expect($result->approved_by_user_id)->toBe($this->user->id);
    expect($result->approved_at)->not->toBeNull();
    expect($result->finance_charge_id)->toBeNull();

    $obligation = FinanceObligation::query()
        ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
        ->where('source_kind', AcademicFinanceObligationSource::COURSE_RETAKE_REGISTRATION)
        ->where('source_ref', AcademicFinanceObligationSource::courseRetakeRegistrationRef($result))
        ->where('obligation_type', FinanceCharge::TYPE_RETAKE_FEE)
        ->firstOrFail();
    $charge = FinanceCharge::query()->where('finance_obligation_id', $obligation->id)->firstOrFail();

    expect($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $obligation->amount)->toBe(1_500_000.0)
        ->and($obligation->pricing_rule_version)->toBe('retake_fee:v1')
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull()
        ->and($charge->charge_type)->toBe(FinanceCharge::TYPE_RETAKE_FEE)
        ->and(InvoiceLine::query()->where('charge_id', $charge->id)->count())->toBe(1);
});

it('rolls back the retake source when finance intake fails', function () {
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
    });

    expect(fn () => CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]))->toThrow(RuntimeException::class, 'finance intake unavailable');

    expect(CourseRetakeRegistration::query()->count())->toBe(0)
        ->and(FinanceObligation::query()->count())->toBe(0)
        ->and(FinanceCharge::query()->count())->toBe(0);
});

it('allows staff-created retake source before class placement', function () {
    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($result->course_offering_id)->toBeNull();
    expect($result->finance_charge_id)->toBeNull();
});

it('keeps failed records eligible even when no class is open yet', function () {
    $this->courseOffering->update(['is_active' => false]);

    $results = app(ListRetakeCourseEligibleStudentsQuery::class)->handle([
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
    ]);

    expect($results)->toHaveCount(1);
    expect($results->first()['student']->is($this->student))->toBeTrue();
    expect($results->first()['unit']->is($this->courseOffering->unit))->toBeTrue();
    expect($results->first()['available_offerings'])->toHaveCount(0);
});

it('calculates attempt_number from existing academic records', function () {
    // The beforeEach already created 1 academic record for this student+unit
    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    // 1 existing academic record + 1 = attempt 2
    expect($result->attempt_number)->toBe(2);
});

it('rejects student not in intake_course status', function () {
    $this->student->update(['status' => 'active']);

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('rejects duplicate active registration for same student+unit+semester', function () {
    // Create first registration
    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    // Attempt duplicate
    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('allows registration after previous one was cancelled', function () {
    // Create and cancel first registration.
    $first = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    // Use full cancel action to properly void charge + cancel registration
    CancelRetakeCourseRegistrationAction::run([
        'registration_id' => $first->id,
        'reason' => 'Test cancellation',
    ]);

    // Should succeed because previous was cancelled (terminal)
    $second = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($second->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
    expect($second->finance_charge_id)->toBeNull();

    $charges = FinanceCharge::where('student_id', $this->student->id)
        ->where('source_type', CourseRetakeRegistration::class)
        ->get();
    expect($charges)->toHaveCount(0);
});

it('rejects registration when unit has zero retake_fee', function () {
    $unit = $this->academicRecord->unit;
    $unit->update(['retake_fee' => 0]);

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('rejects registration when unit has null retake_fee', function () {
    $unit = $this->academicRecord->unit;
    $unit->update(['retake_fee' => null]);

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('snapshots retake_fee from unit', function () {
    // Set retake fee on unit
    $unit = $this->academicRecord->unit;
    $unit->update(['retake_fee' => 7500000]);

    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $unit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect((float) $result->retake_fee)->toBe(7500000.00);
});

it('rejects retake when student has not passed prerequisite unit', function () {
    $prereqUnit = Unit::factory()->create(['retake_fee' => 500000]);
    $targetUnit = $this->courseOffering->unit;

    // Create prerequisite group: targetUnit requires prereqUnit
    $group = UnitPrerequisiteGroup::create([
        'unit_id' => $targetUnit->id,
        'logic_operator' => 'AND',
        'description' => 'Must pass prereq unit first',
    ]);
    UnitPrerequisiteCondition::create([
        'group_id' => $group->id,
        'type' => 'prerequisite',
        'required_unit_id' => $prereqUnit->id,
    ]);

    // Student has NOT completed prereqUnit (no academic_record with completion_status=completed)

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $targetUnit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('rejects retake when academic record failed by grade only', function () {
    // ACAD-RET-001 Slice 2: grade-only failures route to exam resit, not course retake.
    $this->academicRecord->update(['failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED]);

    CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
})->throws(ValidationException::class);

it('allows retake when academic record failed by attendance', function () {
    $this->academicRecord->update(['failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED]);

    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('allows retake when academic record failed by both grade and attendance', function () {
    $this->academicRecord->update(['failure_reason' => AcademicRecord::FAILURE_BOTH_FAILED]);

    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('allows retake when academic record failed manually', function () {
    // Mirror parity with exam resit: a staff-forced failure is staff discretion, not auto-routed.
    $this->academicRecord->update(['failure_reason' => AcademicRecord::FAILURE_MANUAL_FAILED]);

    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('allows retake for legacy failed record with null failure_reason', function () {
    // Forward-only: un-backfilled historical failures stay eligible (no broken staff workflow).
    expect($this->academicRecord->failure_reason)->toBeNull();

    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $this->courseOffering->unit_id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});

it('excludes grade-only failures from the retake eligibility list', function () {
    $this->academicRecord->update(['failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED]);

    $results = app(ListRetakeCourseEligibleStudentsQuery::class)->handle([
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
    ]);

    expect($results)->toHaveCount(0);
});

it('keeps attendance failures in the retake eligibility list', function () {
    $this->academicRecord->update(['failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED]);

    $results = app(ListRetakeCourseEligibleStudentsQuery::class)->handle([
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
    ]);

    expect($results)->toHaveCount(1);
    expect($results->first()['student']->is($this->student))->toBeTrue();
});

it('keeps legacy null-failure-reason failures in the retake eligibility list', function () {
    expect($this->academicRecord->failure_reason)->toBeNull();

    $results = app(ListRetakeCourseEligibleStudentsQuery::class)->handle([
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
    ]);

    expect($results)->toHaveCount(1);
    expect($results->first()['student']->is($this->student))->toBeTrue();
});

it('allows retake when student has passed prerequisite unit', function () {
    $prereqUnit = Unit::factory()->create(['retake_fee' => 500000]);
    $targetUnit = $this->courseOffering->unit;

    // Create prerequisite group: targetUnit requires prereqUnit
    $group = UnitPrerequisiteGroup::create([
        'unit_id' => $targetUnit->id,
        'logic_operator' => 'AND',
        'description' => 'Must pass prereq unit first',
    ]);
    UnitPrerequisiteCondition::create([
        'group_id' => $group->id,
        'type' => 'prerequisite',
        'required_unit_id' => $prereqUnit->id,
    ]);

    // Student HAS completed prereqUnit — create a course offering for the prereq unit
    $prereqOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $prereqUnit->id,
    ]);
    AcademicRecord::factory()->create([
        'student_id' => $this->student->id,
        'campus_id' => $this->campus->id,
        'unit_id' => $prereqUnit->id,
        'course_offering_id' => $prereqOffering->id,
        'completion_status' => 'completed',
        'is_passed' => true,
    ]);

    $result = CreateRetakeCourseRegistrationAction::run([
        'student_id' => $this->student->id,
        'unit_id' => $targetUnit->id,
        'original_academic_record_id' => $this->academicRecord->id,
        'course_offering_id' => $this->courseOffering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);

    expect($result)->toBeInstanceOf(CourseRetakeRegistration::class);
    expect($result->status)->toBe(CourseRetakeRegistration::STATUS_PAYMENT_PENDING);
});
