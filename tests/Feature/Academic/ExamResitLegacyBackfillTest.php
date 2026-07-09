<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\ExamResitAttempt;
use App\Models\ExamResitSession;
use App\Models\ExamRoomSlot;
use App\Models\ExamRoomSlotInvigilator;
use App\Models\Lecture;
use App\Models\Room;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Unit;
use App\Models\User;
use App\Modules\Academic\Actions\BackfillLegacyExamResitCompletionAction;
use App\Modules\Academic\Actions\BackfillLegacyExamResitScheduleAction;
use App\Modules\Finance\Actions\CreateExamResitChargeSimpleAction;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\Legacy\CreateLegacyExamResitChargeFromPaidPtlAction;
use App\Modules\Finance\Actions\Legacy\ReconcileLegacyExamResitFeesAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * ACAD-RET-001 Slice 6 — legacy `exam_resit_fee` backfill / reconciliation.
 *
 * A legacy charge is an active `exam_resit_fee` FinanceCharge that predates the
 * Academic exam-resit source contract, so it carries no ExamResitAttempt link.
 * Reconciliation links each safely matchable charge to a new legacy ExamResitAttempt
 * source and reports the rest as exceptions instead of guessing.
 */
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
});

/**
 * A failed, finalized academic record that is eligible for exam resit
 * (grade-fail lane, attendance evidence recorded).
 *
 * @param  array<string, mixed>  $overrides
 */
function resitUnit(array $overrides = []): Unit
{
    return Unit::factory()->create(array_merge(['code' => 'TEC002'], $overrides));
}

function failedGradeRecord(Unit $unit, array $overrides = []): AcademicRecord
{
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'campus_id' => test()->campus->id,
    ]);

    return AcademicRecord::factory()->create(array_merge([
        'student_id' => test()->student->id,
        'campus_id' => test()->campus->id,
        'semester_id' => test()->semester->id,
        'unit_id' => $unit->id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
        'failure_reason' => AcademicRecord::FAILURE_GRADE_FAILED,
        'total_not_recorded' => 0,
    ], $overrides));
}

/**
 * A legacy `exam_resit_fee` charge with NO Academic source (source_type/id null),
 * created through the normal Finance charge path so it has a payable invoice line.
 *
 * @param  array<string, mixed>  $opts
 */
function legacyExamResitCharge(array $opts = []): FinanceCharge
{
    return app(CreateFinanceChargeAction::class)->handle([
        'student_id' => test()->student->id,
        'semester_id' => test()->semester->id,
        'charge_type' => FinanceCharge::TYPE_EXAM_RESIT_FEE,
        'amount' => $opts['amount'] ?? 750_000,
        'description' => $opts['description'] ?? 'Phí thi lại (legacy import)',
    ]);
}

function legacyApprovedAttempt(Unit $unit, array $overrides = []): ExamResitAttempt
{
    return makeApprovedExamResitAttempt(
        test()->student,
        test()->campus,
        test()->semester,
        array_merge([
            'unit' => $unit,
            'hq_fee_status' => ExamResitAttempt::HQ_FEE_PAID,
            'paid_at' => now(),
            'policy_snapshot' => ['legacy_backfill' => true],
        ], $overrides),
    );
}

function paidPtlDngRequest(array $opts = []): DngPaymentRequest
{
    return DngPaymentRequest::create([
        'student_id' => test()->student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => 'STU'.test()->student->id,
        'item_id' => $opts['item_id'] ?? 'PTL-'.test()->student->id,
        'fee_type' => 'PTL',
        'description' => $opts['description'] ?? 'Exam Retake Fee: TEC001',
        'semester_id' => test()->semester->id,
        'amount' => $opts['amount'] ?? 3_000_000,
        'status' => $opts['status'] ?? DngPaymentRequest::STATUS_PAID_INVOICED,
        'finance_charge_id' => $opts['finance_charge_id'] ?? null,
    ]);
}

it('reconciles a paid legacy charge into a paid legacy-linked exam-resit attempt', function () {
    $unit = resitUnit();
    $record = failedGradeRecord($unit);
    $charge = legacyExamResitCharge();
    payExamResitChargeFully($charge);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['checked'])->toBe(1)
        ->and($result['reconciled'])->toBe(1)
        ->and($result['exceptions'])->toBe(0);

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->first();
    expect($attempt)->not->toBeNull()
        ->and($attempt->academic_record_id)->toBe($record->id)
        ->and($attempt->unit_id)->toBe($unit->id)
        ->and($attempt->student_id)->toBe($this->student->id)
        ->and($attempt->original_semester_id)->toBe($record->semester_id)
        ->and($attempt->charge_semester_id)->toBe($charge->semester_id)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull()
        ->and((float) $attempt->fee_amount)->toBe(750_000.0)
        ->and($attempt->policy_snapshot['legacy_backfill'] ?? null)->toBeTrue();

    // The charge is repointed to the new Academic source so future idempotency and
    // Fee Monitor inference see a real exam-resit source.
    $charge->refresh();
    expect($charge->source_type)->toBe(ExamResitAttempt::class)
        ->and($charge->source_id)->toBe($attempt->id);
});

it('reconciles an unpaid legacy charge as charge_created (payment evidence derived)', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $charge = legacyExamResitCharge();

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_CHARGE_CREATED)
        ->and($attempt->paid_at)->toBeNull();
});

it('reports an exception when the student has no eligible failed record', function () {
    // No failed record at all (e.g. the student already passed the resit unit).
    $charge = legacyExamResitCharge();

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['checked'])->toBe(1)
        ->and($result['reconciled'])->toBe(0)
        ->and($result['exceptions'])->toBe(1);

    expect(ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->exists())->toBeFalse();

    $exception = collect($result['details'])->firstWhere('charge_id', $charge->id);
    expect($exception['status'])->toBe('exception')
        ->and($exception['reason'])->toBe('no_eligible_failed_record');
});

it('reports an exception when the only failed record is an attendance failure (resit-ineligible)', function () {
    $unit = resitUnit();
    failedGradeRecord($unit, ['failure_reason' => AcademicRecord::FAILURE_ATTENDANCE_FAILED]);
    $charge = legacyExamResitCharge();

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['exceptions'])->toBe(1)
        ->and($result['reconciled'])->toBe(0)
        ->and(ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->exists())->toBeFalse();
});

it('reports an exception when failed records are not resit units (TEC001/TEC002)', function () {
    failedGradeRecord(Unit::factory()->create(['code' => 'BUS101']));
    $charge = legacyExamResitCharge(['description' => 'Phí thi lại (no unit named)']);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['exceptions'])->toBe(1)
        ->and($result['reconciled'])->toBe(0);

    $exception = collect($result['details'])->firstWhere('charge_id', $charge->id);
    expect($exception['reason'])->toBe('no_eligible_failed_record');
});

it('disambiguates multiple eligible units by unit code in the charge description', function () {
    $target = Unit::factory()->create(['code' => 'TEC002']);
    failedGradeRecord($target);
    failedGradeRecord(Unit::factory()->create(['code' => 'TEC001']));
    $charge = legacyExamResitCharge(['description' => 'Phí thi lại: TEC002 - Technical Module 2']);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($result['exceptions'])->toBe(0)
        ->and($attempt->unit_id)->toBe($target->id);
});

it('does not touch charges already linked to an Academic exam-resit source', function () {
    $attempt = makeApprovedExamResitAttempt($this->student, $this->campus, $this->semester);
    app(CreateExamResitChargeSimpleAction::class)->handle(['attempt_id' => $attempt->id]);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['checked'])->toBe(0)
        ->and($result['reconciled'])->toBe(0)
        ->and(ExamResitAttempt::query()->count())->toBe(1);
});

it('skips a voided legacy charge', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $charge = legacyExamResitCharge();
    $charge->void($this->user->id, 'cancelled');

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($result['checked'])->toBe(0)
        ->and($result['reconciled'])->toBe(0);
});

it('writes nothing during a dry run', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $charge = legacyExamResitCharge();

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run(dryRun: true);

    expect($result['reconciled'])->toBe(1);

    $charge->refresh();
    expect(ExamResitAttempt::query()->count())->toBe(0)
        ->and($charge->source_type)->toBeNull();
});

it('is idempotent — a second run creates no further attempts', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    legacyExamResitCharge();

    $first = app(ReconcileLegacyExamResitFeesAction::class)->run();
    $second = app(ReconcileLegacyExamResitFeesAction::class)->run();

    expect($first['reconciled'])->toBe(1)
        ->and($second['checked'])->toBe(0)
        ->and($second['reconciled'])->toBe(0)
        ->and(ExamResitAttempt::query()->count())->toBe(1);
});

it('runs end-to-end through the artisan command', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $charge = legacyExamResitCharge();

    $this->artisan('academic:reconcile-legacy-exam-resit-fees')
        ->assertExitCode(0);

    expect(ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->exists())->toBeTrue();
});

it('the artisan command --dry-run writes nothing', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    legacyExamResitCharge();

    $this->artisan('academic:reconcile-legacy-exam-resit-fees', ['--dry-run' => true])
        ->assertExitCode(0);

    expect(ExamResitAttempt::query()->count())->toBe(0);
});

it('reconciles a passed backfilled TEC002 record when the student already completed the resit', function () {
    $unit = resitUnit();
    $record = failedGradeRecord($unit, [
        'is_passed' => true,
        'completion_status' => 'completed',
    ]);
    $charge = legacyExamResitCharge(['description' => 'Phí thi lại (legacy import)']);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($attempt->academic_record_id)->toBe($record->id)
        ->and($attempt->unit_id)->toBe($unit->id);
});

it('defaults to TEC002 when the student studied both resit units', function () {
    failedGradeRecord(Unit::factory()->create(['code' => 'TEC001']));
    $tec002 = resitUnit();
    $target = failedGradeRecord($tec002);
    $charge = legacyExamResitCharge(['description' => 'Phí thi lại (no unit named)']);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($attempt->academic_record_id)->toBe($target->id)
        ->and($attempt->unit_id)->toBe($tec002->id);
});

it('creates an exam_resit_fee charge from a paid PTL DNG request without a finance link', function () {
    $dng = paidPtlDngRequest(['description' => 'Exam Retake Fee']);

    $result = app(CreateLegacyExamResitChargeFromPaidPtlAction::class)->run();

    expect($result['checked'])->toBe(1)
        ->and($result['created'])->toBe(1);

    $dng->refresh();
    $charge = FinanceCharge::query()->findOrFail($dng->finance_charge_id);

    expect($charge->charge_type)->toBe(FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->and($charge->description)->toBe('Exam Retake Fee: TEC002')
        ->and((float) $charge->amount)->toBe(3_000_000.0);
});

it('reconciles a charge linked to paid PTL as paid even when FinanceCharge paid_amount is zero', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    $dng = paidPtlDngRequest();

    app(CreateLegacyExamResitChargeFromPaidPtlAction::class)->run();

    $charge = FinanceCharge::query()->findOrFail($dng->fresh()->finance_charge_id);

    $result = app(ReconcileLegacyExamResitFeesAction::class)->run();

    $attempt = ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->firstOrFail();

    expect($result['reconciled'])->toBe(1)
        ->and($attempt->hq_fee_status)->toBe(ExamResitAttempt::HQ_FEE_PAID)
        ->and($attempt->paid_at)->not->toBeNull();
});

it('creates charge and reconciles end-to-end through the artisan command for paid PTL without charge', function () {
    $unit = resitUnit();
    failedGradeRecord($unit);
    paidPtlDngRequest();

    $this->artisan('academic:reconcile-legacy-exam-resit-fees')
        ->assertExitCode(0);

    $charge = FinanceCharge::query()
        ->where('student_id', $this->student->id)
        ->where('charge_type', FinanceCharge::TYPE_EXAM_RESIT_FEE)
        ->where('status', FinanceCharge::STATUS_ACTIVE)
        ->sole();

    expect(ExamResitAttempt::query()->where('finance_charge_id', $charge->id)->exists())->toBeTrue();
});

it('backfills legacy exam-resit schedule with slot, session, invigilator, and scheduled attempt', function () {
    Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 50]);
    $invigilatorUser = User::factory()->create();
    Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'user_id' => $invigilatorUser->id,
    ]);

    $unit = resitUnit();
    $attempt = legacyApprovedAttempt($unit);

    $result = app(BackfillLegacyExamResitScheduleAction::class)->run(
        examDate: '2026-04-20',
        startTime: '09:00',
        endTime: '11:00',
        invigilatorUserId: $invigilatorUser->id,
        actorUserId: $this->user->id,
    );

    expect($result['checked'])->toBe(1)
        ->and($result['scheduled'])->toBe(1)
        ->and($result['slots_created'])->toBe(1)
        ->and($result['sessions_created'])->toBe(1)
        ->and($result['invigilators_assigned'])->toBe(1);

    $attempt->refresh();
    $slot = ExamRoomSlot::query()->whereDate('exam_date', '2026-04-20')->sole();
    $session = ExamResitSession::query()->where('exam_room_slot_id', $slot->id)->sole();

    expect($attempt->status)->toBe(ExamResitAttempt::STATUS_SCHEDULED)
        ->and($attempt->exam_resit_session_id)->toBe($session->id)
        ->and($attempt->scheduled_at?->format('Y-m-d H:i'))->toBe('2026-04-20 09:00')
        ->and($attempt->policy_snapshot['legacy_schedule_backfill'] ?? null)->toBeTrue()
        ->and($slot->start_time->format('H:i:s'))->toBe('09:00:00')
        ->and($session->unit_id)->toBe($unit->id)
        ->and($session->actual_candidates)->toBe(1)
        ->and(ExamRoomSlotInvigilator::query()->where('exam_room_slot_id', $slot->id)->exists())->toBeTrue();
});

it('is idempotent for legacy schedule backfill', function () {
    Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 50]);
    $invigilatorUser = User::factory()->create();
    Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'user_id' => $invigilatorUser->id,
    ]);
    legacyApprovedAttempt(resitUnit());

    $action = app(BackfillLegacyExamResitScheduleAction::class);
    $first = $action->run('2026-04-20', '09:00', '11:00', $invigilatorUser->id, $this->user->id);
    $second = $action->run('2026-04-20', '09:00', '11:00', $invigilatorUser->id, $this->user->id);

    expect($first['scheduled'])->toBe(1)
        ->and($second['checked'])->toBe(0)
        ->and($second['scheduled'])->toBe(0)
        ->and(ExamRoomSlot::query()->count())->toBe(1)
        ->and(ExamResitSession::query()->count())->toBe(1);
});

it('completes legacy scheduled attempts from the current academic record score', function () {
    Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 50]);
    $invigilatorUser = User::factory()->create();
    Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'user_id' => $invigilatorUser->id,
    ]);

    $unit = resitUnit();
    $record = failedGradeRecord($unit, [
        'final_percentage' => 72.5,
        'final_letter_grade' => 'B',
        'is_passed' => true,
        'completion_status' => 'completed',
    ]);
    $attempt = legacyApprovedAttempt($unit, ['academic_record_id' => $record->id]);

    app(BackfillLegacyExamResitScheduleAction::class)->run(
        '2026-04-20',
        '09:00',
        '11:00',
        $invigilatorUser->id,
        $this->user->id,
    );

    $result = app(BackfillLegacyExamResitCompletionAction::class)->run(
        completedAt: '2026-04-20 11:00:00',
        actorUserId: $this->user->id,
    );

    $attempt->refresh();
    $slot = ExamRoomSlot::query()->sole();

    expect($result['completed'])->toBe(1)
        ->and($result['slots_closed'])->toBe(1)
        ->and($attempt->status)->toBe(ExamResitAttempt::STATUS_COMPLETED)
        ->and((float) $attempt->resit_score)->toBe(72.5)
        ->and($attempt->attempt_number)->toBe(1)
        ->and($attempt->policy_snapshot['legacy_completion_backfill'] ?? null)->toBeTrue()
        ->and($slot->fresh()->status)->toBe(ExamRoomSlot::STATUS_COMPLETED);
});

it('excludes named student codes from legacy completion backfill', function () {
    Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 50]);
    $invigilatorUser = User::factory()->create();
    Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'user_id' => $invigilatorUser->id,
    ]);

    $this->student->update(['student_id' => 'SKIPME001']);
    $unit = resitUnit();
    failedGradeRecord($unit, ['final_percentage' => 55]);
    legacyApprovedAttempt($unit);

    app(BackfillLegacyExamResitScheduleAction::class)->run(
        '2026-04-20',
        '09:00',
        '11:00',
        $invigilatorUser->id,
        $this->user->id,
    );

    $result = app(BackfillLegacyExamResitCompletionAction::class)->run(
        completedAt: '2026-04-20 11:00:00',
        actorUserId: $this->user->id,
        excludeStudentCodes: ['SKIPME001'],
    );

    expect($result['checked'])->toBe(0)
        ->and($result['completed'])->toBe(0)
        ->and(ExamResitAttempt::query()->where('status', ExamResitAttempt::STATUS_SCHEDULED)->count())->toBe(1);
});

it('runs legacy schedule backfill through the artisan command', function () {
    Room::factory()->create(['campus_id' => $this->campus->id, 'capacity' => 50]);
    $invigilatorUser = User::factory()->create();
    Lecture::factory()->create([
        'campus_id' => $this->campus->id,
        'user_id' => $invigilatorUser->id,
    ]);
    $attempt = legacyApprovedAttempt(resitUnit());

    $this->artisan('academic:backfill-legacy-exam-resit-schedule', [
        '--invigilator-user-id' => $invigilatorUser->id,
        '--actor-user-id' => $this->user->id,
    ])->assertExitCode(0);

    expect($attempt->fresh()->status)->toBe(ExamResitAttempt::STATUS_SCHEDULED);
});
