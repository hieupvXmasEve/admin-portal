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
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\FinanceIntakeContract;
use App\Shared\Contracts\Finance\SettlementPositionReader;
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

    return new ReserveAndPushSingleFeeDngAction(
        app(SettlementPositionReader::class),
        $campusResolver,
        $service,
    );
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
        ->and((int) $this->billingAccount->fresh()->settlement_version)->toBeGreaterThan(0);
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

it('reuses a deterministic ItemId for a retry of the same pending reservation', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount);
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->twice()->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    $action = guardedReservationAction($service);

    $first = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));
    $first->update(['status' => DngPaymentRequest::STATUS_PENDING]);
    $second = $action->handle($this->student->id, 'HL', guardedReservationDetails($this->semester));

    expect($second->id)->toBe($first->id)
        ->and($second->item_id)->toBe($first->item_id)
        ->and(DngPaymentRequest::query()->where('active_slot_key', $first->active_slot_key)->count())->toBe(1);
});

it('fails closed without a provider call when the canonical target is invalid', function (): void {
    reservationPayableLine($this->invoice, $this->billingAccount, '0.00');
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldNotReceive('pushReserved');

    expect(fn () => guardedReservationAction($service)->handle($this->student->id, 'HL', guardedReservationDetails($this->semester)))
        ->toThrow(RuntimeException::class);
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
        ->and($reservation->error_message)->toContain('targets changed');
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
        ->and((int) $this->billingAccount->fresh()->settlement_version)->toBe(2);
});
