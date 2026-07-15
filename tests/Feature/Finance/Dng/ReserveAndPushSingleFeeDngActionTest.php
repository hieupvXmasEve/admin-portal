<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\ReserveAndPushSingleFeeDngAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Dng\Services\DngReservationLifecycle;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->campus = Campus::factory()->create(['dng_code' => 'FAUHN']);
    $this->semester = Semester::factory()->create();
    $this->student = Student::factory()->forCampus($this->campus)->create([
        'intake' => 2024,
        'intake_semester_id' => $this->semester->id,
        'status' => 'intake_course',
    ]);
    $this->billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $this->student->id]);
    $this->invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-RSV-'.uniqid(),
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
    ]);
});

function reservationPayableLine(StudentInvoice $invoice, BillingAccount $billingAccount, string $amount = '1500000.00', string $chargeType = FinanceCharge::TYPE_RETAKE_FEE): InvoiceLine
{
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'finance-test',
        'source_kind' => 'guarded_dng_reservation',
        'source_ref' => uniqid('target:', true),
        'obligation_type' => $chargeType,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $invoice->student_id,
        'semester_id' => $invoice->semester_id,
        'charge_type' => $chargeType,
        'amount' => $amount,
        'description' => 'Retake reservation target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Retake reservation line',
        'status' => 'active',
    ]);
}

function guardedReservationAction(DngPaymentService $service): ReserveAndPushSingleFeeDngAction
{
    $campusResolver = Mockery::mock(DngCampusCodeResolver::class);
    $campusResolver->shouldReceive('requireForStudent')->andReturn('FAUHN');

    return new ReserveAndPushSingleFeeDngAction(new DngReservationLifecycle(
        app(SettlementPositionReader::class),
        $campusResolver,
        $service,
    ));
}

function guardedReservationDetails(Semester $semester): array
{
    return [
        'description' => 'Phí học lại',
        'semester_id' => $semester->id,
        'due_date' => now()->addDays(7)->toDateString(),
        'estimate_time' => '07/26',
    ];
}

it('reserves exact canonical HL targets and finalizes outside the reservation transaction', function (): void {
    $lineOne = reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $lineTwo = reservationPayableLine($this->invoice, $this->billingAccount, '500000.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);

    $reservation = guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($reservation->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and($reservation->provider_rail)->toBe('dng')
        ->and((float) $reservation->amount)->toBe(1_500_000.0)
        ->and($reservation->active_slot_key)->toBe('dng:FAUHN:'.$this->billingAccount->id.':HL')
        ->and($reservation->reservationTargets)->toHaveCount(2)
        ->and($reservation->reservationTargets->pluck('invoice_line_id')->all())->toContain($lineOne->id, $lineTwo->id)
        ->and((int) $this->billingAccount->fresh()->settlement_version)->toBe(2);
});

it('uses the same guarded reservation for the HP fee family', function (): void {
    $line = reservationPayableLine($this->invoice, $this->billingAccount, '2000000.00', FinanceCharge::TYPE_TUITION_TERM);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);

    $reservation = guardedReservationAction($service)->handle($this->student->id, 'HP', guardedReservationDetails($this->semester));

    expect($reservation->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and((float) $reservation->amount)->toBe(2_000_000.0)
        ->and($reservation->reservationTargets->pluck('invoice_line_id')->all())->toBe([$line->id]);
});

it('uses guarded canonical targets for every remaining supported fee family', function (string $feeType, string $chargeType): void {
    $line = reservationPayableLine($this->invoice, $this->billingAccount, '2000000.00', $chargeType);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);

    $reservation = guardedReservationAction($service)->handle($this->student->id, $feeType, guardedReservationDetails($this->semester));

    expect($reservation->status)->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and($reservation->fee_type)->toBe($feeType)
        ->and($reservation->reservationTargets->pluck('invoice_line_id')->all())->toBe([$line->id]);
})->with([
    'exam resit' => ['PTL', FinanceCharge::TYPE_EXAM_RESIT_FEE],
    'health insurance' => ['BHYT', FinanceCharge::TYPE_BHYT],
    'other supported fee' => ['KHAC', FinanceCharge::TYPE_MANUAL_FEE],
]);

it('reuses an exact active reservation with one durable deterministic item identity', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $first = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $second = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($second->id)->toBe($first->id)
        ->and($second->item_id)->toBe($first->item_id)
        ->and(DngPaymentRequest::query()->where('active_slot_key', $first->active_slot_key)->count())->toBe(1);
});

it('guards concurrent slot contenders with the database unique slot constraint', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);

    $reservation = guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect(fn () => DngPaymentRequest::query()->create([
        'student_id' => $this->student->id,
        'billing_account_id' => $this->billingAccount->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => $this->student->student_id,
        'fee_type' => 'HL',
        'description' => 'Concurrent contender',
        'semester_id' => $this->semester->id,
        'due_date' => now()->addDays(7),
        'item_id' => 'concurrent-'.$reservation->id,
        'active_slot_key' => $reservation->active_slot_key,
        'amount' => '1500000.00',
        'status' => DngPaymentRequest::STATUS_PENDING,
    ]))->toThrow(QueryException::class);
    expect(DngPaymentRequest::query()->where('active_slot_key', $reservation->active_slot_key)->count())->toBe(1);
});

it('holds an active retry with a different invoice-line target for reconciliation', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $first = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $first->update(['status' => DngPaymentRequest::STATUS_PENDING]);
    reservationPayableLine($this->invoice, $this->billingAccount, '500000.00');

    $second = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($second->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and(DngPaymentRequest::query()->count())->toBe(1)
        ->and($second->review_evidence['current_target_fingerprint'])->toBeString();
});

it('promotes a target-drifted unknown outcome to staff review', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $first = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $first->update(['status' => DngPaymentRequest::STATUS_UNKNOWN_OUTCOME]);
    reservationPayableLine($this->invoice, $this->billingAccount, '500000.00');

    $second = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($second->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and(DngPaymentRequest::query()->count())->toBe(1);
});

it('holds an active retry when its semester changes', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $first = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $first->update(['status' => DngPaymentRequest::STATUS_PENDING]);
    $nextSemester = Semester::factory()->create();
    $nextInvoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-RSV-NEXT-'.uniqid(),
        'student_id' => $this->student->id,
        'semester_id' => $nextSemester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(14),
    ]);
    reservationPayableLine($nextInvoice, $this->billingAccount, '1000000.00');

    $second = $action->handle(
        $this->student->id,
        'HL',
        array_replace(guardedReservationDetails($this->semester), ['semester_id' => $nextSemester->id]),
    );

    expect($second->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW);
});

it('holds an active retry when its exact installment target changes', function (): void {
    $line = reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $firstInstallment = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $line->charge_id,
        'installment_no' => 1,
        'amount' => '500000.00',
    ]);
    $secondInstallment = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $line->charge_id,
        'installment_no' => 2,
        'amount' => '500000.00',
    ]);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $first = $action->handle(
        $this->student->id,
        'HL',
        guardedReservationDetails($this->semester),
        [$line->id],
        [$line->id => '500000.00'],
        [$line->id => $firstInstallment->id],
    );
    $first->update(['status' => DngPaymentRequest::STATUS_PENDING]);

    $second = $action->handle(
        $this->student->id,
        'HL',
        guardedReservationDetails($this->semester),
        [$line->id],
        [$line->id => '500000.00'],
        [$line->id => $secondInstallment->id],
    );

    expect($second->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW);
});

it('holds an active retry when its amount and target fingerprint change', function (): void {
    $line = reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $first = $action->handle(
        $this->student->id,
        'HL',
        guardedReservationDetails($this->semester),
        [$line->id],
        [$line->id => '500000.00'],
    );
    $first->update(['status' => DngPaymentRequest::STATUS_PENDING]);

    $second = $action->handle(
        $this->student->id,
        'HL',
        guardedReservationDetails($this->semester),
        [$line->id],
        [$line->id => '600000.00'],
    );

    expect($second->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW);
});

it('holds a stale settlement-version retry for staff reconciliation', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $first = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $first->update(['status' => DngPaymentRequest::STATUS_PENDING]);
    $this->billingAccount->increment('settlement_version');

    $second = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($second->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and($second->review_evidence)->toMatchArray([
            'deterministic_identity' => $first->item_id,
            'original_target_fingerprint' => $first->target_fingerprint,
            'current_target_fingerprint' => $first->target_fingerprint,
        ]);
});

it('releases a terminal active slot without erasing DNG provider evidence', function (string $terminalStatus): void {
    reservationPayableLine($this->invoice, $this->billingAccount);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn([
        'Code' => 1,
        'Type' => 'success',
        'Message' => 'ok',
        'data' => ['TransactionID' => 'TXN-TERM', 'PaymentId' => 'PAY-TERM'],
    ]);

    $request = guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $request->transitionTo($terminalStatus);
    $terminal = $request->fresh();

    expect($terminal->active_slot_key)->toBeNull()
        ->and($terminal->item_id)->not->toBeNull()
        ->and($terminal->dng_transaction_id)->toBe('TXN-TERM')
        ->and($terminal->reservationTargets)->toHaveCount(1);
})->with([
    'paid' => DngPaymentRequest::STATUS_PAID_UNINVOICED,
    'cancelled' => DngPaymentRequest::STATUS_CANCELLED,
    'failed' => DngPaymentRequest::STATUS_FAILED,
]);

it('allows a new request to occupy a released active slot', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->twice()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $cancelled = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $cancelled->transitionTo(DngPaymentRequest::STATUS_CANCELLED);
    $replacement = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($replacement->id)->not->toBe($cancelled->id)
        ->and($cancelled->fresh()->active_slot_key)->toBeNull()
        ->and($replacement->active_slot_key)->toBe('dng:FAUHN:'.$this->billingAccount->id.':HL');
});

it('does not reactivate a failed terminal reservation after releasing its active slot', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);

    $request = guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $request->transitionTo(DngPaymentRequest::STATUS_FAILED);

    expect($request->fresh()->active_slot_key)->toBeNull()
        ->and($request->fresh()->canTransitionTo(DngPaymentRequest::STATUS_PENDING))->toBeFalse();
});

it('fails closed without a provider call when the canonical target is invalid', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount, '0.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldNotReceive('pushReserved');

    expect(fn () => guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester)))
        ->toThrow(RuntimeException::class);
    expect(DngPaymentRequest::query()->count())->toBe(0);
});

it('fails before the provider call when a target drifts during local reservation', function (): void {
    $line = reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $realReader = app(SettlementPositionReader::class);
    $reader = Mockery::mock(SettlementPositionReader::class);
    $reads = 0;
    $reader->shouldReceive('forPayableLines')->twice()->andReturnUsing(function (array $lineIds) use (&$reads, $line, $realReader) {
        $reads++;
        if ($reads === 2) {
            $line->update(['amount_snapshot' => '0.00']);
        }

        return $realReader->forPayableLines($lineIds);
    });
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldNotReceive('pushReserved');
    $campusResolver = Mockery::mock(DngCampusCodeResolver::class);
    $campusResolver->shouldReceive('requireForStudent')->andReturn('FAUHN');
    $action = new ReserveAndPushSingleFeeDngAction(new DngReservationLifecycle($reader, $campusResolver, $service));

    expect(fn () => $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester)))
        ->toThrow(RuntimeException::class, 'Settlement Position changed while reserving DNG targets');
    expect(DngPaymentRequest::query()->count())->toBe(0);
});

it('holds the request for review when its exact target changes before finalization', function (): void {
    $line = reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturnUsing(function () use ($line): array {
        $line->update(['amount_snapshot' => '900000.00']);

        return ['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []];
    });

    $reservation = guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($reservation->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and($reservation->reservationTargets)->toHaveCount(1)
        ->and($reservation->error_message)->toContain('targets changed')
        ->and($reservation->review_evidence)->toMatchArray([
            'provider_response' => ['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []],
            'deterministic_identity' => $reservation->item_id,
            'original_target_fingerprint' => $reservation->target_fingerprint,
            'affected_installment_ids' => [],
        ])
        ->and($reservation->review_evidence['current_target_fingerprint'])->toBeString()->not->toBe($reservation->target_fingerprint);
});

it('records a deterministic current fingerprint when every target disappears before finalization', function (): void {
    $line = reservationPayableLine($this->invoice, $this->billingAccount, '1000000.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturnUsing(function () use ($line): array {
        $line->update(['amount_snapshot' => '0.00']);

        return ['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []];
    });

    $reservation = guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($reservation->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and($reservation->review_evidence['current_target_fingerprint'])->toBeString()->not->toBe('')
        ->and($reservation->review_evidence['current_target_fingerprint'])->not->toBe($reservation->target_fingerprint);
});

it('blocks a concurrent credit application during the unlocked DNG provider call without over-collecting or losing a settlement version', function (): void {
    $line = reservationPayableLine($this->invoice, $this->billingAccount, '1500000.00');
    $credit = null;
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->once()->andReturnUsing(function () use (&$credit, $line): array {
        $credit = app(FinanceIntakeContract::class)->requestCredit(new FinanceIntakeData(
            source_system: 'finance-test',
            source_kind: 'credit_dng_race',
            source_ref: uniqid('credit-dng:', true),
            financial_effect: FinancialEffect::Credit,
            obligation_type: FinanceEntitlementType::DeferCredit,
            facts: [
                'student_id' => $this->student->id,
                'semester_id' => $this->semester->id,
                'amount' => 500_000,
                'invoice_line_id' => $line->id,
            ],
        ));

        return ['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []];
    });

    $reservation = guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($credit)->not->toBeNull()
        ->and($credit->credit_application_ids)->toBe([])
        ->and((float) $reservation->amount)->toBe(1_500_000.0)
        ->and((int) $this->billingAccount->fresh()->settlement_version)->toBe(3);
});
