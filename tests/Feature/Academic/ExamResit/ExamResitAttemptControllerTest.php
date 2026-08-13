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
use App\Modules\Academic\Support\AcademicFinanceObligationSource;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $this->semester->id,
    ]);

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['view_exam_resit', 'create_exam_resit', 'cancel_exam_resit']);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);

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

function gradeFailedRecordForController(): AcademicRecord
{
    $unit = Unit::factory()->create();
    $syllabus = SyllabusTemplate::create([
        'unit_id' => $unit->id,
        'title' => 'Exam resit policy',
        'version' => '1.0',
        'total_hours' => 120,
        'total_sessions' => 30,
        'learning_outcomes' => ['x'],
        'grading_criteria' => [['name' => 'Final', 'weight' => 100]],
        'required_materials' => [],
        'is_default' => true,
        'is_active' => true,
        'created_by' => test()->user->id,
        'exam_resit_max_attempts' => 1,
        'exam_resit_late_payment_grace_days' => 14,
        'exam_resit_allow_unpaid_sitting' => false,
    ]);
    $offering = CourseOffering::factory()->create([
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => test()->campus->id,
        'syllabus_template_id' => $syllabus->id,
    ]);

    return AcademicRecord::factory()->create([
        'student_id' => test()->student->id,
        'campus_id' => test()->campus->id,
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $offering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'override_pass' => false,
        'final_percentage' => 48,
        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
        'total_not_recorded' => 0,
        'failure_reason_snapshot' => ['attendance_evidence_state' => 'recorded'],
    ]);
}

function controllerExamResitCharge(ExamResitAttempt $attempt): FinanceCharge
{
    $obligationId = FinanceObligation::query()
        ->where('source_system', AcademicFinanceObligationSource::SOURCE_SYSTEM)
        ->where('source_kind', AcademicFinanceObligationSource::EXAM_RESIT_ATTEMPT)
        ->where('source_ref', AcademicFinanceObligationSource::examResitAttemptRef($attempt))
        ->value('id');

    return FinanceCharge::query()->where('finance_obligation_id', $obligationId)->firstOrFail();
}

it('renders the exam-resit worklist with scalar filter defaults', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.exam-resit.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/ExamResit/Index')
            ->where('filters.search', '')
            ->where('filters.status', null)
            ->where('filters.operation_state', null)
            ->where('filters.semester_id', null)
            ->where('filters.unit_id', null)
            ->where('filters.per_page', 15)
            ->has('attempts.data')
            ->has('summary'));
});

it('normalizes scalar filters from exam-resit list query params', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.exam-resit.index', [
            'search' => 'AUH14972',
            'operation_state' => 'scheduled',
            'semester_id' => $this->semester->id,
            'per_page' => 25,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/ExamResit/Index')
            ->where('filters.search', 'AUH14972')
            ->where('filters.operation_state', 'scheduled')
            ->where('filters.semester_id', $this->semester->id)
            ->where('filters.per_page', 25));
});

it('queues finance cancellation through the controller and flashes success', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.cancel', $attempt->id), ['_token' => 'test-token', 'reason' => 'Sinh viên xin rút'])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION);
});

it('rejects paid cancellation without no-refund acknowledgement', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = controllerExamResitCharge($attempt);
    payExamResitChargeFully($charge);
    $attempt->transitionToPaid();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.cancel', $attempt->id), [
            '_token' => 'test-token',
            'reason' => 'Sinh viên xin rút',
        ])
        ->assertSessionHasErrors('acknowledge_no_refund');

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_APPROVED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('rejects unpaid charge-created cancellation without fee confirmation', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = controllerExamResitCharge($attempt);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.cancel', $attempt->id), [
            '_token' => 'test-token',
            'reason' => 'Sinh viên xin rút',
        ])
        ->assertSessionHasErrors('confirmation');

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_APPROVED)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE);
});

it('queues paid cancellation through the controller when no-refund is acknowledged', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();
    $charge = controllerExamResitCharge($attempt);
    payExamResitChargeFully($charge);
    $attempt->transitionToPaid();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.cancel', $attempt->id), [
            '_token' => 'test-token',
            'reason' => 'Sinh viên xin rút',
            'acknowledge_no_refund' => true,
        ])
        ->assertRedirect();

    $attempt->refresh();
    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_FINANCE_PENDING_CANCELLATION)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->cancellation_fee_disposition)->toBeNull()
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($charge->fresh()->void_reason)->toBeNull();

    $payment = PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->where('entry_type', 'application')
        ->firstOrFail()
        ->payment()
        ->firstOrFail();

    expect(PaymentApplication::query()
        ->whereIn('invoice_line_id', $charge->invoiceLines()->pluck('id'))
        ->sum('amount'))->toBe('750000.00')
        ->and($payment->unapplied_amount)->toBe(0.0);

    expect(app(GetStudentBalanceQuery::class)->handle($this->student->id)['unapplied_credit'])->toBe(0.0);
});

it('renders the create page with eligible students', function () {
    gradeFailedRecordForController();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.exam-resit.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/ExamResit/Create')
            ->has('eligible_students')
            ->has('semesters'));
});

it('surfaces the active semester as current_semester_id on the create page', function () {
    $this->semester->update(['is_active' => true]);
    Semester::factory()->create(['is_active' => false]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id])
        ->get(route('academic.exam-resit.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Academic/ExamResit/Create')
            ->where('current_semester_id', $this->semester->id));
});

it('stores an exam-resit attempt from a grade-failed record', function () {
    $record = gradeFailedRecordForController();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.store'), [
            '_token' => 'test-token',
            'student_id' => $this->student->id,
            'academic_record_id' => $record->id,
            'operation_semester_id' => $this->semester->id,
            'charge_semester_id' => $this->semester->id,
            'campus_id' => $this->campus->id,
        ])
        ->assertRedirect(route('academic.exam-resit.index'));

    expect(ExamResitAttempt::where('academic_record_id', $record->id)->where('status', ExamResitAttempt::STATUS_APPROVED)->exists())->toBeTrue();
});

it('bulk-stores exam-resit attempts for multiple eligible records in one submit', function () {
    $studentOne = $this->student;
    $recordOne = gradeFailedRecordForController();

    $studentTwo = Student::factory()->forCampus($this->campus)->create([
        'status' => 'intake_course', 'intake' => 1, 'intake_mode' => 'sequential', 'intake_semester_id' => $this->semester->id,
    ]);
    test()->student = $studentTwo;
    $recordTwo = gradeFailedRecordForController();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.store-bulk'), [
            '_token' => 'test-token',
            'items' => [
                ['student_id' => $studentOne->id, 'academic_record_id' => $recordOne->id, 'campus_id' => $this->campus->id],
                ['student_id' => $studentTwo->id, 'academic_record_id' => $recordTwo->id, 'campus_id' => $this->campus->id],
            ],
            'operation_semester_id' => $this->semester->id,
            'charge_semester_id' => $this->semester->id,
        ])
        ->assertRedirect();

    expect(ExamResitAttempt::where('academic_record_id', $recordOne->id)->where('status', ExamResitAttempt::STATUS_APPROVED)->exists())->toBeTrue()
        ->and(ExamResitAttempt::where('academic_record_id', $recordTwo->id)->where('status', ExamResitAttempt::STATUS_APPROVED)->exists())->toBeTrue();
});

it('collects per-item failures in a bulk submit without blocking the rest of the batch', function () {
    $eligibleRecord = gradeFailedRecordForController();

    $passedUnit = Unit::factory()->create();
    $passedOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'unit_id' => $passedUnit->id,
        'campus_id' => $this->campus->id,
    ]);
    $alreadyPassedRecord = AcademicRecord::factory()->create([
        'student_id' => $this->student->id,
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $passedUnit->id,
        'course_offering_id' => $passedOffering->id,
        'completion_status' => 'completed',
        'grade_status' => 'final',
        'is_passed' => true,
        'failure_reason' => null,
    ]);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.store-bulk'), [
            '_token' => 'test-token',
            'items' => [
                ['student_id' => $this->student->id, 'academic_record_id' => $eligibleRecord->id, 'campus_id' => $this->campus->id],
                ['student_id' => $this->student->id, 'academic_record_id' => $alreadyPassedRecord->id, 'campus_id' => $this->campus->id],
            ],
            'operation_semester_id' => $this->semester->id,
            'charge_semester_id' => $this->semester->id,
        ])
        ->assertRedirect();

    $flash = session('inertia.flash_data', []);

    expect($flash)->toHaveKey('warning')
        ->and($flash['warning'])->toContain('thành công 1')
        ->and($flash['warning'])->toContain('thất bại 1');

    expect(ExamResitAttempt::where('academic_record_id', $eligibleRecord->id)->where('status', ExamResitAttempt::STATUS_APPROVED)->exists())->toBeTrue()
        ->and(ExamResitAttempt::where('academic_record_id', $alreadyPassedRecord->id)->exists())->toBeFalse();
});

it('blocks a duplicate item in the same bulk submit from double-registering the same record', function () {
    $record = gradeFailedRecordForController();

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.store-bulk'), [
            '_token' => 'test-token',
            'items' => [
                ['student_id' => $this->student->id, 'academic_record_id' => $record->id, 'campus_id' => $this->campus->id],
                ['student_id' => $this->student->id, 'academic_record_id' => $record->id, 'campus_id' => $this->campus->id],
            ],
            'operation_semester_id' => $this->semester->id,
            'charge_semester_id' => $this->semester->id,
        ])
        ->assertRedirect();

    expect(ExamResitAttempt::where('academic_record_id', $record->id)->count())->toBe(1)
        ->and(FinanceCharge::where('charge_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)->count())->toBe(1);
});

it('rejects an empty bulk submit', function () {
    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.store-bulk'), [
            '_token' => 'test-token',
            'items' => [],
            'operation_semester_id' => $this->semester->id,
            'charge_semester_id' => $this->semester->id,
        ])
        ->assertSessionHasErrors('items');
});

it('rejects cancel without a reason', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.cancel', $attempt->id), ['_token' => 'test-token', 'reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_APPROVED);
});
