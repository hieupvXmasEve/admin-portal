<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CurriculumVersion;
use App\Models\DeferCase;
use App\Models\DeferCaseItem;
use App\Models\FinanceCharge;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\FixBillingExceptionAction;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\ListBillingExceptionsQuery;
use App\Modules\Finance\Support\BillingExceptionIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    app()->singleton('campus', fn () => $this->campus);
});

it('lists missing charge exceptions with stable encoded ids', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-MISS-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
            'intake_gc' => $this->semester->id,
            'intake_course' => $this->semester->id,
            'intake_major' => $this->semester->id,
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    $registration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);
    $list = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'missing_charge', 'http://localhost/exceptions');

    expect($counts['missing_charge'])->toBe(1)
        ->and($list->total())->toBe(1)
        ->and($list->items()[0]['type'])->toBe('missing_charge')
        ->and($list->items()[0]['student_code'])->toBe('EXC-MISS-01')
        ->and($list->items()[0]['id'])->toBe(
            BillingExceptionIdentifier::encode('missing_charge', $registration->id)
        )
        ->and($list->items()[0]['fixable'])->toBeTrue();
});

it('fixes a retake no charge exception by creating a retake fee charge', function () {
    $user = User::factory()->create();
    actingAs($user);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-FIX-RET-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
            'intake_gc' => $this->semester->id,
            'intake_course' => $this->semester->id,
            'intake_major' => $this->semester->id,
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    $registration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
        'retake_fee' => 1500000,
    ]);

    $exceptionId = BillingExceptionIdentifier::encode('retake_no_charge', $registration->id);

    $result = FixBillingExceptionAction::run(['exception_id' => $exceptionId]);

    expect($result['fixed'])->toBeTrue();

    expect(
        FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->exists()
    )->toBeTrue();
});

it('does not create duplicate retake charges when the same exception is fixed twice', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-FIX-RET-02',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    $registration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
        'retake_fee' => 1500000,
    ]);

    $exceptionId = BillingExceptionIdentifier::encode('retake_no_charge', $registration->id);

    $first = FixBillingExceptionAction::run(['exception_id' => $exceptionId]);
    $second = FixBillingExceptionAction::run(['exception_id' => $exceptionId]);

    $charges = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->where('source_type', CourseRegistration::class)
        ->where('source_id', $registration->id)
        ->get();

    expect($charges)->toHaveCount(1)
        ->and($first['charge_id'])->toBe($charges->first()->id)
        ->and($second['charge_id'])->toBe($charges->first()->id);
});

it('fixes a missing charge exception by creating a tuition term charge', function () {
    actingAs(User::factory()->create());

    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-FIX-MISS-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_gc' => $this->semester->id,
            'intake_course' => (string) $this->semester->id,
            'intake_major' => $this->semester->id,
        ])
        ->create();

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $this->semester->id,
        'total_amount' => 45000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45000000,
        'due_date' => now()->addDays(30)->toDateString(),
    ]);

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    $registration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    $exceptionId = BillingExceptionIdentifier::encode('missing_charge', $registration->id);

    $result = FixBillingExceptionAction::run([
        'exception_id' => $exceptionId,
        'semester_id' => $this->semester->id,
    ]);

    expect($result['fixed'])->toBeTrue();

    expect(
        FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('semester_id', $this->semester->id)
            ->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->exists()
    )->toBeTrue();
});

it('refuses a missing charge fix when the requested semester does not match the registration', function () {
    $otherSemester = Semester::factory()->create();
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-MISS-WRONG-SEM',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    $registration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    $exceptionId = BillingExceptionIdentifier::encode('missing_charge', $registration->id);

    expect(fn () => FixBillingExceptionAction::run([
        'exception_id' => $exceptionId,
        'semester_id' => $otherSemester->id,
    ]))->toThrow(RuntimeException::class, 'Requested semester does not match the exception registration');
});

it('detects retake no charge exceptions per registration source', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-MULTI-RET',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $firstOffering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);
    $secondOffering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);
    $firstRegistration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $firstOffering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
        'retake_fee' => 1500000,
    ]);
    $secondRegistration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $secondOffering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 3,
        'is_retake' => true,
        'retake_fee' => 1500000,
    ]);

    FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 1500000,
        'description' => 'Retake',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRegistration::class,
        'source_id' => $firstRegistration->id,
    ]);

    $list = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'retake_no_charge', 'http://localhost/exceptions');

    expect($list->total())->toBe(1)
        ->and($list->items()[0]['context']['course_registration_id'])->toBe($secondRegistration->id);
});

it('refuses a retake fix when defer policy skips charge creation', function () {
    $user = User::factory()->create();
    actingAs($user);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-DEFER-RET-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    $registration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
        'retake_fee' => 1500000,
    ]);

    $actionLog = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Defer preserve retake fee',
        'from_semester_id' => $this->semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $deferCase = DeferCase::create([
        'student_action_log_id' => $actionLog->id,
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'scope_type' => DeferCase::SCOPE_COURSES,
        'fee_policy' => DeferCase::POLICY_PRESERVE,
        'applies_once' => true,
        'effective_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    DeferCaseItem::create([
        'defer_case_id' => $deferCase->id,
        'course_registration_id' => $registration->id,
        'fee_policy' => DeferCase::POLICY_PRESERVE,
    ]);

    $exceptionId = BillingExceptionIdentifier::encode('retake_no_charge', $registration->id);

    expect(fn () => FixBillingExceptionAction::run(['exception_id' => $exceptionId]))
        ->toThrow(RuntimeException::class, 'Retake charge skipped by defer policy');

    expect(
        FinanceCharge::query()
            ->where('student_id', $student->id)
            ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->exists()
    )->toBeFalse();
});

it('refuses defer_no_case auto-fix with a clear unsupported message', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'intake' => 1,
            'intake_mode' => 'sequential',
            'intake_semester_id' => $this->semester->id,
            'status' => 'intake_course',
        ])
        ->create();

    $log = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Academic defer for testing',
        'from_semester_id' => $this->semester->id,
        'changed_by_user_id' => User::factory()->create()->id,
    ]);

    $exceptionId = BillingExceptionIdentifier::encode('defer_no_case', $log->id);

    expect(fn () => FixBillingExceptionAction::run(['exception_id' => $exceptionId]))
        ->toThrow(RuntimeException::class, 'Defer case creation requires manual fee-policy selection');
});
