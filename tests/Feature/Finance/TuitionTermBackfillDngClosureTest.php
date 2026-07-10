<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CancelDngPaymentRequestAction;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Actions\Major\SubmitTuitionTermDebitAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $this->campus);
});

function makeTuitionStudent(Campus $campus, Semester $semester, string $code): Student
{
    return Student::factory()->forCampus($campus)->create([
        'student_id' => $code,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
        'full_name' => "Student {$code}",
        'email' => strtolower($code).'@example.com',
    ]);
}

function makeTuitionLegacyCharge(
    Student $student,
    Semester $semester,
    float $amount,
    string $status = FinanceCharge::STATUS_ACTIVE,
): FinanceCharge {
    return FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Legacy tuition',
        'effective_at' => now(),
        'status' => $status,
        'source_type' => 'MajorChargeBatch',
        'source_id' => null,
        'created_by_user_id' => test()->user->id,
        'voided_at' => $status === FinanceCharge::STATUS_VOID ? now() : null,
        'voided_by_user_id' => $status === FinanceCharge::STATUS_VOID ? test()->user->id : null,
        'void_reason' => $status === FinanceCharge::STATUS_VOID ? 'legacy void' : null,
    ]);
}

function attachTuitionInvoiceLine(FinanceCharge $charge, float $amountSnapshot): InvoiceLine
{
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-TUI-'.uniqid(),
        'student_id' => $charge->student_id,
        'semester_id' => $charge->semester_id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => $amountSnapshot,
        'discount_total' => 0,
        'total_amount' => $amountSnapshot,
        'paid_amount' => 0,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amountSnapshot,
        'description_snapshot' => $charge->description,
        'status' => 'active',
    ]);
}

function makeTuitionBatchDngAction(bool $dngFails = false): CreateBatchDngFromChargesAction
{
    $dngPaymentServiceMock = Mockery::mock(DngPaymentService::class);

    if ($dngFails) {
        $dngPaymentServiceMock->shouldReceive('createAndPush')
            ->andThrow(new RuntimeException('DNG API failed'));
    } else {
        $dngPaymentServiceMock->shouldReceive('createAndPush')
            ->andReturnUsing(function (Student $student, array $data) {
                return DngPaymentRequest::create([
                    'student_id' => $student->id,
                    'campus_code' => $data['campus_code'],
                    'student_code' => $data['student_code'],
                    'fee_type' => $data['fee_type'],
                    'item_id' => $data['item_id'],
                    'amount' => $data['amount'],
                    'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
                    'description' => $data['description'] ?? null,
                    'semester_id' => $data['semester_id'] ?? null,
                    'due_date' => $data['due_date'] ?? null,
                ]);
            });
    }

    $campusResolverMock = Mockery::mock(DngCampusCodeResolver::class);
    $campusResolverMock->shouldReceive('requireForStudent')->andReturn('FAUHN');

    return new CreateBatchDngFromChargesAction(
        $dngPaymentServiceMock,
        $campusResolverMock,
        app(CancelDngPaymentRequestAction::class),
    );
}

// ─── Legacy backfill ────────────────────────────────────────────────────────

it('backfills active and voided legacy tuition_term charges with legacy_backfill provenance and is idempotent', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI2001');
    $active = makeTuitionLegacyCharge($student, $this->semester, 45_000_000, FinanceCharge::STATUS_ACTIVE);
    attachTuitionInvoiceLine($active, 45_000_000);
    $voided = makeTuitionLegacyCharge($student, $this->semester, 40_000_000, FinanceCharge::STATUS_VOID);

    // Untouched other debit type
    $bhyt = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_BHYT,
        'amount' => 564_000,
        'description' => 'BHYT',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $this->artisan('finance:backfill-legacy-tuition-term-obligations')
        ->expectsOutputToContain('created 2')
        ->assertExitCode(0);

    $activeObligation = FinanceObligation::query()
        ->where('source_ref', FinanceOwnedObligationSource::legacyTuitionTermChargeRef($active->id))
        ->firstOrFail();
    $voidedObligation = FinanceObligation::query()
        ->where('source_ref', FinanceOwnedObligationSource::legacyTuitionTermChargeRef($voided->id))
        ->firstOrFail();

    expect(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(2)
        ->and($activeObligation->source_system)->toBe(FinanceOwnedObligationSource::SOURCE_SYSTEM)
        ->and($activeObligation->source_kind)->toBe(SubmitTuitionTermDebitAction::SOURCE_KIND_LEGACY_TUITION)
        ->and($activeObligation->obligation_type)->toBe(FinanceCharge::TYPE_TUITION_TERM)
        ->and($activeObligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $activeObligation->amount)->toBe(45_000_000.0)
        ->and($activeObligation->pricing_rule_version)->toBe('tuition_term:legacy_backfill')
        ->and($activeObligation->pricing_snapshot['provenance'])->toBe('legacy_backfill')
        ->and($activeObligation->pricing_snapshot['legacy_charge_id'])->toBe($active->id)
        ->and($activeObligation->billing_account_id)->not->toBeNull()
        ->and($voidedObligation->lifecycle_status)->toBe(FinanceObligation::STATUS_VOIDED)
        ->and((float) $voidedObligation->amount)->toBe(40_000_000.0)
        ->and($voidedObligation->pricing_snapshot['provenance'])->toBe('legacy_backfill')
        ->and($active->fresh()->finance_obligation_id)->toBe($activeObligation->id)
        ->and($voided->fresh()->finance_obligation_id)->toBe($voidedObligation->id)
        ->and($bhyt->fresh()->finance_obligation_id)->toBeNull();

    $this->artisan('finance:backfill-legacy-tuition-term-obligations')
        ->expectsOutputToContain('already linked 2')
        ->assertExitCode(0);

    expect(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(2)
        ->and($active->fresh()->finance_obligation_id)->toBe($activeObligation->id)
        ->and($voided->fresh()->finance_obligation_id)->toBe($voidedObligation->id);
});

it('hard-gates when derived settlement outstanding drifts from old computed balance', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI2002');
    $charge = makeTuitionLegacyCharge($student, $this->semester, 45_000_000);
    // Invoice line snapshot drifts from charge.amount → settlement payable/outstanding diverge.
    attachTuitionInvoiceLine($charge, 40_000_000);

    $this->artisan('finance:backfill-legacy-tuition-term-obligations')
        ->expectsOutputToContain('mismatch(es) 1')
        ->expectsOutputToContain('Reconciliation hard-gate failed')
        ->assertExitCode(1);

    // Obligation is still created (ops can inspect), but gate fails the command.
    expect($charge->fresh()->finance_obligation_id)->not->toBeNull()
        ->and(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(1);
});

it('passes hard-gate when settlement outstanding matches old balance', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI2003');
    $charge = makeTuitionLegacyCharge($student, $this->semester, 45_000_000);
    attachTuitionInvoiceLine($charge, 45_000_000);

    $this->artisan('finance:backfill-legacy-tuition-term-obligations')
        ->expectsOutputToContain('mismatch(es) 0')
        ->assertExitCode(0);
});

// ─── DNG guard ──────────────────────────────────────────────────────────────

it('HP fee_type: blocks push when tuition_term charge has no finance obligation', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI3001');
    $orphan = makeTuitionLegacyCharge($student, $this->semester, 45_000_000);
    attachTuitionInvoiceLine($orphan, 45_000_000);

    $action = makeTuitionBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'Tuition DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'][0])->toContain('missing_finance_obligation')
        ->and($result['errors'][0])->toContain('backfill-legacy-tuition-term-obligations')
        ->and($orphan->fresh()->finance_obligation_id)->toBeNull()
        ->and(DngPaymentRequest::where('student_id', $student->id)->count())->toBe(0)
        ->and(FinanceCharge::where('student_id', $student->id)->where('charge_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(1);
});

it('HP fee_type: pushes DNG only from existing obligation-linked tuition payables', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI3002');
    $charge = makeTuitionLegacyCharge($student, $this->semester, 45_000_000);
    attachTuitionInvoiceLine($charge, 45_000_000);

    $this->artisan('finance:backfill-legacy-tuition-term-obligations')->assertExitCode(0);
    expect($charge->fresh()->finance_obligation_id)->not->toBeNull();

    $action = makeTuitionBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'Tuition DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and(DngPaymentRequest::where('student_id', $student->id)->where('fee_type', 'HP')->count())->toBe(1);
});

it('HP fee_type: ignores retired course_fee payables (no free-pass, not in reverse map)', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI3003');
    $courseFee = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_COURSE_FEE,
        'amount' => 2_000_000,
        'description' => 'Course fee (retired type fixture)',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
        'created_by_user_id' => $this->user->id,
    ]);
    attachTuitionInvoiceLine($courseFee, 2_000_000);

    $action = makeTuitionBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'Course fee DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    // course_fee is formally retired: excluded from HP reverse map, so batch
    // finds no HP payables and does not free-pass orphan course_fee rows.
    expect($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'][0])->toContain('Không tìm thấy khoản phí')
        ->and($courseFee->fresh()->finance_obligation_id)->toBeNull()
        ->and(DngPaymentRequest::where('student_id', $student->id)->where('fee_type', 'HP')->count())->toBe(0);
});

it('HP fee_type: fails with no payables when student has no HP charges (no auto-create)', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI3004');

    $action = makeTuitionBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'HP',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'Tuition DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'][0])->toContain('Không tìm thấy khoản phí')
        ->and(FinanceCharge::where('student_id', $student->id)->count())->toBe(0)
        ->and(FinanceObligation::where('obligation_type', FinanceCharge::TYPE_TUITION_TERM)->count())->toBe(0)
        ->and(DngPaymentRequest::where('student_id', $student->id)->count())->toBe(0);
});

it('does not invent billing accounts for dry-run backfill', function (): void {
    $student = makeTuitionStudent($this->campus, $this->semester, 'TUI2004');
    $charge = makeTuitionLegacyCharge($student, $this->semester, 45_000_000);
    attachTuitionInvoiceLine($charge, 45_000_000);

    $this->artisan('finance:backfill-legacy-tuition-term-obligations --dry-run')
        ->expectsOutputToContain('[dry-run] Checked 1')
        ->assertExitCode(0);

    expect($charge->fresh()->finance_obligation_id)->toBeNull()
        ->and(FinanceObligation::query()->count())->toBe(0);
});
