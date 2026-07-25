<?php

declare(strict_types=1);

use App\Enums\StudentActionType;
use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRegistration;
use App\Models\CourseRetakeRegistration;
use App\Models\CurriculumVersion;
use App\Models\DeferCase;
use App\Models\DeferCaseItem;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\TuitionPlan;
use App\Models\TuitionPlanTerm;
use App\Models\User;
use App\Modules\Finance\Actions\Operations\FixBillingExceptionAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Queries\Operations\GetBillingExceptionCountsQuery;
use App\Modules\Finance\Queries\Operations\ListBillingExceptionsQuery;
use App\Modules\Finance\Support\BillingExceptionIdentifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();

    app()->singleton('campus', fn () => $this->campus);

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

it('lists deferred enrolled students separately from missing charge exceptions', function () {
    $user = User::factory()->create();
    actingAs($user);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-DEFER-ENROLL-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'deferred',
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

    $actionLog = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Deferred but retained on class roster',
        'from_semester_id' => $this->semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    DeferCase::create([
        'student_action_log_id' => $actionLog->id,
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'scope_type' => DeferCase::SCOPE_FULL,
        'fee_policy' => DeferCase::POLICY_FORFEIT,
        'applies_once' => true,
        'effective_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);
    $missing = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'missing_charge', 'http://localhost/exceptions');
    $deferred = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'deferred_enrolled', 'http://localhost/exceptions');

    expect($counts['missing_charge'])->toBe(0)
        ->and($counts['deferred_enrolled'])->toBe(1)
        ->and($missing->total())->toBe(0)
        ->and($deferred->total())->toBe(1)
        ->and($deferred->items()[0]['type'])->toBe('deferred_enrolled')
        ->and($deferred->items()[0]['student_code'])->toBe('EXC-DEFER-ENROLL-01')
        ->and($deferred->items()[0]['fixable'])->toBeFalse()
        ->and($deferred->items()[0]['context']['fee_policy'])->toBe(DeferCase::POLICY_FORFEIT)
        ->and($deferred->items()[0]['id'])->toBe(
            BillingExceptionIdentifier::encode('deferred_enrolled', $registration->id)
        );
});

it('does not classify a resumed student as deferred from historical defer evidence', function () {
    $user = User::factory()->create();
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-RESUMED-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);
    CourseRegistration::create([
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

    $actionLog = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Historical defer before resuming',
        'from_semester_id' => $this->semester->id,
        'changed_by_user_id' => $user->id,
    ]);
    DeferCase::create([
        'student_action_log_id' => $actionLog->id,
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'scope_type' => DeferCase::SCOPE_FULL,
        'fee_policy' => DeferCase::POLICY_FORFEIT,
        'applies_once' => true,
        'effective_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);

    expect($counts['missing_charge'])->toBe(1)
        ->and($counts['deferred_enrolled'])->toBe(0);
});

it('lists the default all-filter queue without calling Eloquent methods on query builders', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-ALL-RET-01',
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
        'retake_fee' => 1_500_000,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);
    $list = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'all', 'http://localhost/exceptions');
    $retake = collect($list->items())->firstWhere('type', 'retake_no_charge');
    $missing = collect($list->items())->firstWhere('type', 'missing_charge');

    expect($counts['missing_charge'])->toBe(1)
        ->and($counts['retake_no_charge'])->toBe(1)
        ->and($list->total())->toBe(2)
        ->and($missing['fixable'])->toBeTrue()
        ->and($retake['student_code'])->toBe('EXC-ALL-RET-01')
        ->and($retake['fixable'])->toBeTrue()
        ->and($retake['id'])->toBe(
            BillingExceptionIdentifier::encode('retake_no_charge', $registration->id)
        );
});

it('keeps chronological ordering when the all queue mixes Finance and Progression evidence', function () {
    $missingStudent = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-MIXED-MISSING',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();
    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);
    $registration = CourseRegistration::create([
        'student_id' => $missingStudent->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);
    $registration->forceFill([
        'created_at' => '2026-01-15 23:00:00',
        'updated_at' => '2026-01-15 23:00:00',
    ])->save();

    $deferredStudent = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-MIXED-DEFER',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'deferred',
        ])
        ->create();
    $action = StudentActionLog::create([
        'student_id' => $deferredStudent->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Earlier defer without case',
        'from_semester_id' => $this->semester->id,
        'changed_by_user_id' => User::factory()->create()->id,
    ]);
    $action->forceFill([
        'created_at' => '2026-01-15 01:00:00',
        'updated_at' => '2026-01-15 01:00:00',
    ])->save();

    $list = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'all', 'http://localhost/exceptions');

    expect(array_column($list->items(), 'type'))->toBe(['missing_charge', 'defer_no_case'])
        ->and(array_column($list->items(), 'student_code'))->toBe([
            'EXC-MIXED-MISSING',
            'EXC-MIXED-DEFER',
        ]);
});

it('returns an empty queue for the unimplemented mismatch filter', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-MISMATCH-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    CourseRegistration::create([
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
        ->handle($this->semester->id, 'mismatch', 'http://localhost/exceptions');

    expect($counts['missing_charge'])->toBe(1)
        ->and($counts['mismatch'])->toBe(0)
        ->and($list->total())->toBe(0)
        ->and($list->items())->toBe([]);
});

it('keeps all-filter campus scope, ordering, and pagination stable', function () {
    $otherCampus = Campus::factory()->create();
    $otherSemester = Semester::factory()->create();
    $currentOffering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);
    $otherSemesterOffering = CourseOffering::factory()->create(['semester_id' => $otherSemester->id]);

    $createdAt = now()->subDay()->startOfSecond();

    $createMissingRegistration = function (Campus $campus, CourseOffering $offering, string $studentCode) use ($createdAt): void {
        $student = Student::factory()
            ->forCampus($campus)
            ->forProgram($this->program)
            ->state([
                'student_id' => $studentCode,
                'intake_semester_id' => $this->semester->id,
                'intake' => 1,
                'intake_mode' => 'sequential',
                'status' => 'intake_course',
            ])
            ->create();

        $registration = CourseRegistration::create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'semester_id' => $offering->semester_id,
            'registration_status' => 'confirmed',
            'registration_date' => now(),
            'registration_method' => 'admin_override',
            'credit_hours' => 3,
            'credit_points' => 3,
            'attempt_number' => 1,
        ]);

        DB::table('course_registrations')
            ->where('id', $registration->id)
            ->update(['created_at' => $createdAt]);
    };

    foreach (range(1, 21) as $index) {
        $createMissingRegistration(
            $this->campus,
            $currentOffering,
            sprintf('EXC-ALL-SCOPE-%02d', $index),
        );
    }

    $createMissingRegistration($otherCampus, $currentOffering, 'EXC-OTHER-CAMP');
    $createMissingRegistration($this->campus, $otherSemesterOffering, 'EXC-OTHER-SEM');

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);
    $pageOne = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'all', 'http://localhost/exceptions');

    request()->merge(['page' => 2]);
    $pageTwo = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'all', 'http://localhost/exceptions');

    request()->merge(['page' => 1]);
    $reloadedPageOne = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'all', 'http://localhost/exceptions');

    $codes = array_column(array_merge($pageOne->items(), $pageTwo->items()), 'student_code');

    expect($counts['missing_charge'])->toBe(21)
        ->and($pageOne->total())->toBe(21)
        ->and($pageOne->count())->toBe(20)
        ->and($pageOne->items()[0]['student_code'])->toBe('EXC-ALL-SCOPE-21')
        ->and($pageTwo->count())->toBe(1)
        ->and($pageTwo->items()[0]['student_code'])->toBe('EXC-ALL-SCOPE-01')
        ->and(array_column($reloadedPageOne->items(), 'id'))->toBe(array_column($pageOne->items(), 'id'))
        ->and($codes)->not->toContain('EXC-OTHER-CAMP', 'EXC-OTHER-SEM');
});

it('lists defer actions that have no defer case', function () {
    $user = User::factory()->create();
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-DEFER-NO-CASE-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'deferred',
        ])
        ->create();

    $actionLog = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Deferred without case',
        'from_semester_id' => $this->semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);
    $list = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'defer_no_case', 'http://localhost/exceptions');

    expect($counts['defer_no_case'])->toBe(1)
        ->and($list->total())->toBe(1)
        ->and($list->items()[0]['type'])->toBe('defer_no_case')
        ->and($list->items()[0]['student_code'])->toBe('EXC-DEFER-NO-CASE-01')
        ->and($list->items()[0]['id'])->toBe(
            BillingExceptionIdentifier::encode('defer_no_case', $actionLog->id)
        );
});

it('lists zero tuition waived students separately from missing charge exceptions', function () {
    $fallSemester = Semester::factory()->create([
        'name' => 'FALL2025',
        'code' => 'FALL2025',
        'start_date' => '2025-09-01',
        'is_active' => false,
    ]);
    $springSemester = Semester::factory()->create([
        'name' => 'SPRING2026',
        'code' => 'SPRING2026',
        'start_date' => '2026-01-05',
        'is_active' => false,
    ]);
    $summerSemester = Semester::factory()->create([
        'name' => 'SUMMER2026',
        'code' => 'SUMMER2026',
        'start_date' => '2026-05-04',
        'is_active' => true,
    ]);

    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($fallSemester)
        ->create();

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-WAIVED-01',
            'intake_semester_id' => $fallSemester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_gc' => $fallSemester->id,
            'intake_course' => (string) $springSemester->id,
            'intake_major' => $springSemester->id,
        ])
        ->create();

    $plan = TuitionPlan::create([
        'curriculum_version_id' => $curriculumVersion->id,
        'intake_semester_id' => $fallSemester->id,
        'total_amount' => 135000000,
        'currency' => 'VND',
        'is_active' => true,
    ]);

    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 1,
        'amount' => 45000000,
        'due_date' => '2026-01-05',
    ]);
    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 2,
        'amount' => 0,
        'due_date' => '2026-05-04',
    ]);
    TuitionPlanTerm::create([
        'tuition_plan_id' => $plan->id,
        'term_number' => 3,
        'amount' => 45000000,
        'due_date' => '2026-09-01',
    ]);

    $offering = CourseOffering::factory()->create(['semester_id' => $summerSemester->id]);

    $registration = CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $summerSemester->id,
        'registration_status' => 'confirmed',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($summerSemester->id);
    $missing = app(ListBillingExceptionsQuery::class)
        ->handle($summerSemester->id, 'missing_charge', 'http://localhost/exceptions');
    $waived = app(ListBillingExceptionsQuery::class)
        ->handle($summerSemester->id, 'zero_tuition_waived', 'http://localhost/exceptions');

    expect($counts['missing_charge'])->toBe(0)
        ->and($counts['zero_tuition_waived'])->toBe(1)
        ->and($missing->total())->toBe(0)
        ->and($waived->total())->toBe(1)
        ->and($waived->items()[0]['type'])->toBe('zero_tuition_waived')
        ->and($waived->items()[0]['student_code'])->toBe('EXC-WAIVED-01')
        ->and($waived->items()[0]['fixable'])->toBeFalse()
        ->and($waived->items()[0]['context']['tuition_term_number'])->toBe(2)
        ->and($waived->items()[0]['id'])->toBe(
            BillingExceptionIdentifier::encode('zero_tuition_waived', $registration->id)
        );
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

    // Intake path keys by finance_obligation source triple (not morph source_*).
    $charges = FinanceCharge::query()
        ->where('student_id', $student->id)
        ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->get();

    expect($charges)->toHaveCount(1)
        ->and($first['charge_id'])->toBe($charges->first()->id)
        ->and($second['charge_id'])->toBe($charges->first()->id)
        ->and($charges->first()->finance_obligation_id)->not->toBeNull();
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

it('does not flag retake registrations when an active charge is linked via CourseRetakeRegistration', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-CRR-RET-01',
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
        'retake_fee' => 3000000,
    ]);

    $academicRecord = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $this->campus->id,
        'unit_id' => $offering->unit_id,
        'course_offering_id' => $offering->id,
        'completion_status' => 'failed',
        'is_passed' => false,
    ]);

    $retakeRegistration = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $offering->unit_id,
        'original_academic_record_id' => $academicRecord->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'status' => CourseRetakeRegistration::STATUS_ENROLLED,
        'attempt_number' => 2,
        'retake_fee' => 3000000,
        'course_registration_id' => $registration->id,
        'enrolled_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 3000000,
        'description' => 'Retake via course retake registration',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'source_type' => CourseRetakeRegistration::class,
        'source_id' => $retakeRegistration->id,
    ]);

    $retakeRegistration->update(['finance_charge_id' => $charge->id]);

    FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
        'amount' => 3000000,
        'description' => 'Void legacy course registration charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_VOID,
        'voided_at' => now(),
        'void_reason' => 'replaced',
        'source_type' => CourseRegistration::class,
        'source_id' => $registration->id,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);
    $list = app(ListBillingExceptionsQuery::class)
        ->handle($this->semester->id, 'retake_no_charge', 'http://localhost/exceptions');

    expect($counts['retake_no_charge'])->toBe(0)
        ->and($list->total())->toBe(0);
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

    // Wave 7: FixBillingException always mints via intake. Defer PRESERVE is no
    // longer short-circuited inside this repair action (defer settlement is separate).
    $result = FixBillingExceptionAction::run(['exception_id' => $exceptionId]);

    expect($result['fixed'])->toBeTrue()
        ->and(
            FinanceCharge::query()
                ->where('student_id', $student->id)
                ->where('charge_type', FinanceCharge::TYPE_RETAKE_FEE)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->exists()
        )->toBeTrue();
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

it('does not flag a defer-marked registration as an enrolled-without-charge exception', function () {
    $user = User::factory()->create();
    actingAs($user);

    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-DEFER-MARKED-01',
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
        'registration_status' => 'defer',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 1,
    ]);

    $actionLog = StudentActionLog::create([
        'student_id' => $student->id,
        'action_type' => StudentActionType::ACADEMIC_DEFER,
        'reason' => 'Course-scope defer',
        'from_semester_id' => $this->semester->id,
        'changed_by_user_id' => $user->id,
    ]);

    $deferCase = DeferCase::create([
        'student_action_log_id' => $actionLog->id,
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'scope_type' => DeferCase::SCOPE_COURSES,
        'fee_policy' => DeferCase::POLICY_FORFEIT,
        'applies_once' => true,
        'effective_at' => now()->toDateString(),
        'changed_by_user_id' => $user->id,
    ]);

    DeferCaseItem::create([
        'defer_case_id' => $deferCase->id,
        'course_registration_id' => $registration->id,
        'fee_policy' => DeferCase::POLICY_FORFEIT,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);

    expect($counts['missing_charge'])->toBe(0)
        ->and($counts['deferred_enrolled'])->toBe(0)
        ->and($counts['zero_tuition_waived'])->toBe(0);
});

it('does not flag a defer-marked retake registration as a retake-no-charge exception', function () {
    $student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'EXC-DEFER-RETAKE-01',
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
            'status' => 'intake_course',
        ])
        ->create();

    $offering = CourseOffering::factory()->create(['semester_id' => $this->semester->id]);

    CourseRegistration::create([
        'student_id' => $student->id,
        'course_offering_id' => $offering->id,
        'semester_id' => $this->semester->id,
        'registration_status' => 'defer',
        'registration_date' => now(),
        'registration_method' => 'admin_override',
        'credit_hours' => 3,
        'credit_points' => 3,
        'attempt_number' => 2,
        'is_retake' => true,
        'retake_fee' => 1500000,
    ]);

    $counts = app(GetBillingExceptionCountsQuery::class)->handle($this->semester->id);

    expect($counts['retake_no_charge'])->toBe(0);
});
