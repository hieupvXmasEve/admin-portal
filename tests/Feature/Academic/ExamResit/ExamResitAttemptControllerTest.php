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
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['view_exam_resit', 'create_exam_resit', 'cancel_exam_resit']);
    app()->singleton(PermissionService::class, fn () => $permissionService);
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
        'exam_resit_fee' => 750000,
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

it('cancels an attempt through the controller and flashes success', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.cancel', $attempt->id), ['_token' => 'test-token', 'reason' => 'Sinh viên xin rút'])
        ->assertRedirect();

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_CANCELLED);
});

it('rejects paid cancellation without no-refund acknowledgement', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();

    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);
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
    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);

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

it('cancels paid attempts through the controller when no-refund is acknowledged', function () {
    Queue::fake();
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);
    $attempt->refresh();

    $charge = FinanceCharge::findOrFail($attempt->finance_charge_id);
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
    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_CANCELLED)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->cancellation_fee_disposition)->toBe(ExamResitAttempt::CANCELLATION_FEE_KEPT_PAID_NO_REFUND)
        ->and($charge->fresh()->status)->toBe(FinanceCharge::STATUS_ACTIVE)
        ->and($charge->fresh()->is_fully_paid)->toBeTrue();
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

it('rejects cancel without a reason', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);

    actingAs($this->user)
        ->withSession(['current_campus_id' => $this->campus->id, '_token' => 'test-token'])
        ->post(route('academic.exam-resit.cancel', $attempt->id), ['_token' => 'test-token', 'reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_APPROVED);
});
