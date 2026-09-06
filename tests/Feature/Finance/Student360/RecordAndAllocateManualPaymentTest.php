<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\FinancePaymentVoucher;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Operations\ListEnrolledUnappliedCashQuery;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Shared\Contracts\Academic\ExamResitAttemptPaymentSyncer;
use App\Shared\Contracts\Academic\RetakeRegistrationPaymentSyncer;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutMiddleware([
        PreventRequestForgery::class,
        VerifyCsrfToken::class,
    ]);

    $this->user = User::factory()->create();
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->active()->create();
    $this->program = Program::factory()->create();
    $this->curriculumVersion = CurriculumVersion::factory()
        ->forProgram($this->program)
        ->withEffectiveSemester($this->semester)
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(CampusPermissionReader::class);
    $permissionService->shouldReceive('permissionCodesForUserId')
        ->andReturn(['create_finance_payments', 'view_finance_payments', 'allocate_finance_payment']);
    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

it('records cash, allocates, issues a voucher, and syncs academic after commit', function () {
    $student = manualPayStudent($this);
    $line = manualPayChargeLine($this, $student, FinanceCharge::TYPE_RETAKE_FEE, 1_500_000, 'RT');
    $syncer = Mockery::mock(RetakeRegistrationPaymentSyncer::class);
    $syncer->shouldReceive('runForStudent')->once()->with($student->id);
    app()->instance(RetakeRegistrationPaymentSyncer::class, $syncer);
    app()->instance(ExamResitAttemptPaymentSyncer::class, Mockery::mock(ExamResitAttemptPaymentSyncer::class)->shouldReceive('runForStudent')->once()->with($student->id)->getMock());

    $key = (string) Str::uuid();
    $this->actingAs($this->user)
        ->post(route('finance.students.payments.store', $student), [
            'amount' => 1_500_000,
            'method' => Payment::METHOD_CASH,
            'idempotency_key' => $key,
            'allocations' => [
                ['charge_id' => $line->charge_id, 'amount' => 1_500_000],
            ],
        ])
        ->assertRedirect();

    $payment = Payment::query()->where('idempotency_key', $key)->firstOrFail();
    $voucher = FinancePaymentVoucher::query()->where('payment_id', $payment->id)->firstOrFail();

    expect((float) PaymentApplication::query()->where('invoice_line_id', $line->id)->sum('amount'))->toEqual(1_500_000)
        ->and($voucher->voucher_number)->toStartWith('PV-')
        ->and($voucher->allocations_snapshot)->toHaveCount(1)
        ->and((float) $voucher->unapplied_amount)->toEqual(0.0);
});

it('replays the same idempotency key without a second payment or voucher', function () {
    $student = manualPayStudent($this);
    $line = manualPayChargeLine($this, $student, FinanceCharge::TYPE_MANUAL_FEE, 500_000, 'IDEM');
    $key = (string) Str::uuid();
    $payload = [
        'amount' => 500_000,
        'method' => Payment::METHOD_CASH,
        'idempotency_key' => $key,
        'allocations' => [
            ['charge_id' => $line->charge_id, 'amount' => 500_000],
        ],
    ];

    $this->actingAs($this->user)->post(route('finance.students.payments.store', $student), $payload)->assertRedirect();
    $this->actingAs($this->user)->post(route('finance.students.payments.store', $student), $payload)->assertRedirect();

    expect(Payment::query()->where('idempotency_key', $key)->count())->toBe(1)
        ->and(FinancePaymentVoucher::query()->count())->toBe(1)
        ->and(PaymentApplication::query()->count())->toBe(1);
});

it('records capped skips for later charges when cash is exhausted', function () {
    $student = manualPayStudent($this);
    $first = manualPayChargeLine($this, $student, FinanceCharge::TYPE_TUITION_TERM, 1_000_000, 'CAP1');
    $second = manualPayChargeLine($this, $student, FinanceCharge::TYPE_MANUAL_FEE, 1_000_000, 'CAP2');

    $this->actingAs($this->user)
        ->post(route('finance.students.payments.store', $student), [
            'amount' => 1_000_000,
            'method' => Payment::METHOD_CASH,
            'idempotency_key' => (string) Str::uuid(),
            'allocations' => [
                ['charge_id' => $first->charge_id, 'amount' => 1_000_000],
                ['charge_id' => $second->charge_id, 'amount' => 1_000_000],
            ],
        ])
        ->assertRedirect();

    $voucher = FinancePaymentVoucher::query()->firstOrFail();
    expect((float) PaymentApplication::query()->where('invoice_line_id', $first->id)->sum('amount'))->toEqual(1_000_000)
        ->and(PaymentApplication::query()->where('invoice_line_id', $second->id)->count())->toBe(0)
        ->and($voucher->skipped_snapshot)->toEqual([
            ['charge_id' => (int) $second->charge_id, 'reason' => FinancePaymentVoucher::SKIP_CAPPED],
        ]);
});

it('keeps voucher snapshot when later ledger applications change', function () {
    $student = manualPayStudent($this);
    $line = manualPayChargeLine($this, $student, FinanceCharge::TYPE_MANUAL_FEE, 800_000, 'SNAP');
    $key = (string) Str::uuid();
    $this->actingAs($this->user)
        ->post(route('finance.students.payments.store', $student), [
            'amount' => 300_000,
            'method' => Payment::METHOD_CASH,
            'idempotency_key' => $key,
            'allocations' => [
                ['charge_id' => $line->charge_id, 'amount' => 300_000],
            ],
        ])
        ->assertRedirect();

    $voucher = FinancePaymentVoucher::query()->firstOrFail();
    $frozen = $voucher->allocations_snapshot;

    PaymentApplication::query()->create([
        'payment_id' => $voucher->payment_id,
        'invoice_line_id' => $line->id,
        'amount' => 50_000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    expect($voucher->fresh()->allocations_snapshot)->toEqual($frozen);
});

it('records dng_held skips on the voucher instead of silent leftover', function () {
    $student = manualPayStudent($this);
    $line = manualPayChargeLine($this, $student, FinanceCharge::TYPE_TUITION_TERM, 1_000_000, 'HOLD');
    $request = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => $this->campus->code,
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'item_id' => 'ITEM-HOLD',
        'amount' => 1_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    DngPaymentRequestReservationTarget::query()->create([
        'dng_payment_request_id' => $request->id,
        'invoice_line_id' => $line->id,
        'captured_collectible' => 1_000_000,
        'target_identity' => 'line:'.$line->id,
    ]);

    $key = (string) Str::uuid();
    $this->actingAs($this->user)
        ->post(route('finance.students.payments.store', $student), [
            'amount' => 1_000_000,
            'method' => Payment::METHOD_CASH,
            'idempotency_key' => $key,
            'allocations' => [
                ['charge_id' => $line->charge_id, 'amount' => 1_000_000],
            ],
        ])
        ->assertRedirect();

    $voucher = FinancePaymentVoucher::query()->firstOrFail();
    expect($voucher->skipped_snapshot)->toEqual([
        ['charge_id' => (int) $line->charge_id, 'reason' => FinancePaymentVoucher::SKIP_DNG_HELD],
    ])
        ->and((float) $voucher->unapplied_amount)->toEqual(1_000_000)
        ->and(PaymentApplication::query()->count())->toBe(0)
        ->and(app(ListEnrolledUnappliedCashQuery::class)->handle($this->campus->id))->toHaveCount(1);
});

it('does not roll back money when an academic syncer throws', function () {
    $student = manualPayStudent($this);
    $line = manualPayChargeLine($this, $student, FinanceCharge::TYPE_RETAKE_FEE, 400_000, 'SYNC');
    $syncer = Mockery::mock(RetakeRegistrationPaymentSyncer::class);
    $syncer->shouldReceive('runForStudent')->once()->andThrow(new RuntimeException('academic down'));
    app()->instance(RetakeRegistrationPaymentSyncer::class, $syncer);
    app()->instance(ExamResitAttemptPaymentSyncer::class, Mockery::mock(ExamResitAttemptPaymentSyncer::class)->shouldReceive('runForStudent')->once()->getMock());
    Log::shouldReceive('error')->once();

    $this->actingAs($this->user)
        ->post(route('finance.students.payments.store', $student), [
            'amount' => 400_000,
            'method' => Payment::METHOD_CASH,
            'idempotency_key' => (string) Str::uuid(),
            'allocations' => [
                ['charge_id' => $line->charge_id, 'amount' => 400_000],
            ],
        ])
        ->assertRedirect();

    expect(Payment::query()->count())->toBe(1)
        ->and(FinancePaymentVoucher::query()->count())->toBe(1)
        ->and(PaymentApplication::query()->count())->toBe(1);
});

it('prints an internal voucher that is not a VAT invoice', function () {
    $student = manualPayStudent($this);
    $line = manualPayChargeLine($this, $student, FinanceCharge::TYPE_MANUAL_FEE, 200_000, 'PRT');
    $this->actingAs($this->user)
        ->post(route('finance.students.payments.store', $student), [
            'amount' => 200_000,
            'method' => Payment::METHOD_CASH,
            'idempotency_key' => (string) Str::uuid(),
            'allocations' => [
                ['charge_id' => $line->charge_id, 'amount' => 200_000],
            ],
        ])
        ->assertRedirect();

    $payment = Payment::query()->firstOrFail();
    $this->actingAs($this->user)
        ->get(route('finance.payments.receipt', $payment))
        ->assertOk()
        ->assertSee('PHIẾU THU NỘI BỘ')
        ->assertSee('không phải hoá đơn GTGT', false);
});

function manualPayStudent(object $test): Student
{
    return Student::factory()
        ->forCampus($test->campus)
        ->forProgram($test->program)
        ->state([
            'curriculum_version_id' => $test->curriculumVersion->id,
            'intake_semester_id' => $test->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
}

function manualPayChargeLine(object $test, Student $student, string $type, float $amount, string $suffix): InvoiceLine
{
    $billingAccount = app(BillingAccountProvisioner::class)->forStudent((int) $student->id);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'manual_payment',
        'source_ref' => "manual-pay:{$student->id}:{$suffix}:{$type}",
        'obligation_type' => $type,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $test->semester->id,
        'charge_type' => $type,
        'amount' => $amount,
        'description' => $type,
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $invoice = StudentInvoice::create([
        'student_id' => $student->id,
        'semester_id' => $test->semester->id,
        'invoice_number' => 'INV-MP-'.$suffix.'-'.$student->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30),
    ]);

    return InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => $type,
        'status' => 'active',
    ]);
}
