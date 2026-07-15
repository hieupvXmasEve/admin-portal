<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Actions\Operations\GenerateNonAcademicChargesAction;
use App\Modules\Finance\Actions\ReserveAndPushSingleFeeDngAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Services\DngReservationLifecycle;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Support\FinanceOwnedObligationSource;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);

    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $this->campus);
});

function makeBhytStudent(Campus $campus, Semester $semester, string $code): Student
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

function makeBhytLegacyCharge(
    Student $student,
    Semester $semester,
    float $amount,
    string $status = FinanceCharge::STATUS_ACTIVE,
): FinanceCharge {
    return FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_BHYT,
        'amount' => $amount,
        'description' => 'Legacy BHYT',
        'effective_at' => now(),
        'status' => $status,
        'source_type' => 'NonAcademicChargeBatch',
        'source_id' => null,
        'created_by_user_id' => test()->user->id,
        'voided_at' => $status === FinanceCharge::STATUS_VOID ? now() : null,
        'voided_by_user_id' => $status === FinanceCharge::STATUS_VOID ? test()->user->id : null,
        'void_reason' => $status === FinanceCharge::STATUS_VOID ? 'legacy void' : null,
    ]);
}

function makeBhytBatchDngAction(bool $dngFails = false): CreateBatchDngFromChargesAction
{
    $dngPaymentServiceMock = Mockery::mock(DngPaymentService::class);

    if ($dngFails) {
        $dngPaymentServiceMock->shouldReceive('pushReserved')
            ->andThrow(new RuntimeException('DNG API failed'));
    } else {
        $dngPaymentServiceMock->shouldReceive('pushReserved')->andReturn([
            'Code' => 200,
            'Type' => 'success',
            'Message' => 'ok',
            'data' => ['TransactionID' => 'BHYT-TEST-TRANSACTION', 'PaymentId' => 'BHYT-TEST-PAYMENT'],
        ]);
    }

    $campusResolverMock = Mockery::mock(DngCampusCodeResolver::class);
    $campusResolverMock->shouldReceive('requireForStudent')->andReturn('FAUHN');

    return new CreateBatchDngFromChargesAction(new ReserveAndPushSingleFeeDngAction(
        new DngReservationLifecycle(
            app(SettlementPositionReader::class),
            $campusResolverMock,
            $dngPaymentServiceMock,
        ),
    ));
}

// ─── Intake cutover ─────────────────────────────────────────────────────────

it('creates BHYT through intake as accepted obligation with charge and invoice line', function (): void {
    $student = makeBhytStudent($this->campus, $this->semester, 'BHYT1001');

    $result = GenerateNonAcademicChargesAction::run([
        'fee_type' => FinanceCharge::TYPE_BHYT,
        'semester_id' => $this->semester->id,
        'amount' => 564_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'note' => 'BHYT 2026',
        'student_codes' => ['BHYT1001'],
    ]);

    expect($result['summary']['created'])->toBe(1)
        ->and($result['created'][0]['student_code'])->toBe('BHYT1001');

    $charge = FinanceCharge::query()->findOrFail($result['created'][0]['charge_id']);
    $obligation = FinanceObligation::query()->findOrFail($charge->finance_obligation_id);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    expect($obligation->source_system)->toBe(FinanceOwnedObligationSource::SOURCE_SYSTEM)
        ->and($obligation->source_kind)->toBe(FinanceOwnedObligationSource::NON_ACADEMIC_BATCH)
        ->and($obligation->source_ref)->toStartWith('non_academic_batch:bhyt:')
        ->and($obligation->obligation_type)->toBe(FinanceCharge::TYPE_BHYT)
        ->and($obligation->lifecycle_status)->toBe(FinanceObligation::STATUS_ACCEPTED)
        ->and((float) $obligation->amount)->toBe(564_000.0)
        ->and($obligation->currency)->toBe('VND')
        ->and($obligation->pricing_rule_version)->toBe('bhyt:generator')
        ->and($obligation->pricing_snapshot['pricing_strategy'])->toBe('generator_amount')
        ->and($obligation->billing_account_id)->not->toBeNull()
        ->and($charge->charge_type)->toBe(FinanceCharge::TYPE_BHYT)
        ->and($charge->student_id)->toBe($student->id)
        ->and($charge->semester_id)->toBe($this->semester->id)
        ->and((float) $charge->amount)->toBe(564_000.0)
        ->and($charge->source_type)->toBeNull()
        ->and($charge->source_id)->toBeNull()
        ->and($charge->description)->toBe('BHYT 2026')
        ->and((float) $line->amount_snapshot)->toBe(564_000.0);
});

it('skips duplicate active BHYT without creating a second obligation', function (): void {
    makeBhytStudent($this->campus, $this->semester, 'BHYT1002');

    GenerateNonAcademicChargesAction::run([
        'fee_type' => FinanceCharge::TYPE_BHYT,
        'semester_id' => $this->semester->id,
        'amount' => 500_000,
        'due_date' => now()->addDays(14)->toDateString(),
        'note' => '',
        'student_codes' => ['BHYT1002'],
    ]);

    $second = GenerateNonAcademicChargesAction::run([
        'fee_type' => FinanceCharge::TYPE_BHYT,
        'semester_id' => $this->semester->id,
        'amount' => 500_000,
        'due_date' => now()->addDays(14)->toDateString(),
        'note' => '',
        'student_codes' => ['BHYT1002'],
    ]);

    expect($second['summary']['created'])->toBe(0)
        ->and($second['summary']['skipped'])->toBe(1)
        ->and($second['skipped'][0]['reason'])->toStartWith('duplicate_existing_charge_id_')
        ->and(FinanceObligation::query()->where('obligation_type', FinanceCharge::TYPE_BHYT)->count())->toBe(1)
        ->and(FinanceCharge::query()->where('charge_type', FinanceCharge::TYPE_BHYT)->count())->toBe(1);
});

// ─── Legacy backfill ────────────────────────────────────────────────────────

it('BHYT fee_type: blocks push when charge has no finance obligation instead of creating debt', function (): void {
    $student = makeBhytStudent($this->campus, $this->semester, 'BHYT3001');
    $orphan = makeBhytLegacyCharge($student, $this->semester, 564_000);

    $action = makeBhytBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'BHYT',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'BHYT DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'][0])->toContain('missing_finance_obligation')
        ->and($orphan->fresh()->finance_obligation_id)->toBeNull()
        ->and(DngPaymentRequest::where('student_id', $student->id)->count())->toBe(0)
        ->and(FinanceCharge::where('student_id', $student->id)->count())->toBe(1);
});

it('BHYT fee_type: pushes DNG only from existing obligation-linked payables', function (): void {
    $student = makeBhytStudent($this->campus, $this->semester, 'BHYT3002');

    $gen = GenerateNonAcademicChargesAction::run([
        'fee_type' => FinanceCharge::TYPE_BHYT,
        'semester_id' => $this->semester->id,
        'amount' => 564_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'note' => 'BHYT linked',
        'student_codes' => ['BHYT3002'],
    ]);

    $charge = FinanceCharge::query()->findOrFail($gen['created'][0]['charge_id']);
    expect($charge->finance_obligation_id)->not->toBeNull();

    $action = makeBhytBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'BHYT',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'BHYT DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(1)
        ->and($result['failed'])->toBe(0)
        ->and(DngPaymentRequest::where('student_id', $student->id)->where('fee_type', 'BHYT')->count())->toBe(1);
});

it('BHYT fee_type: fails with no payables when student has no BHYT charges (no auto-create)', function (): void {
    $student = makeBhytStudent($this->campus, $this->semester, 'BHYT3003');

    $action = makeBhytBatchDngAction();
    $result = $action->handle([
        'student_ids' => [$student->id],
        'dng_fee_type' => 'BHYT',
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $this->semester->id,
        'description' => 'BHYT DNG',
        'estimate_time' => now()->addDays(7)->toDateTimeString(),
        'amount_overrides' => null,
    ]);

    expect($result['created'])->toBe(0)
        ->and($result['failed'])->toBe(1)
        ->and($result['errors'][0])->toContain('Không tìm thấy khoản phí')
        ->and(FinanceCharge::where('student_id', $student->id)->count())->toBe(0)
        ->and(FinanceObligation::where('obligation_type', FinanceCharge::TYPE_BHYT)->count())->toBe(0)
        ->and(DngPaymentRequest::where('student_id', $student->id)->count())->toBe(0);
});
