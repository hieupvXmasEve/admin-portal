<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Actions\CreateBatchDngFromChargesAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Services\DngCampusCodeResolver;
use App\Modules\Finance\Dng\Services\DngPaymentService;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function batchDngStudent(Campus $campus, Semester $semester): Student
{
    return Student::factory()->forCampus($campus)->create([
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
        'status' => 'intake_course',
    ]);
}

function batchCanonicalLine(Student $student, Semester $semester, string $type, string $amount): InvoiceLine
{
    $account = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $account->id,
        'source_system' => 'finance-test',
        'source_kind' => 'batch_dng',
        'source_ref' => uniqid('batch:', true),
        'obligation_type' => $type,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::query()->create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => $type,
        'amount' => $amount,
        'description' => 'Canonical DNG target',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-BATCH-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
    ]);

    return InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Canonical DNG target',
        'status' => 'active',
    ]);
}

function batchDngPayload(Student $student, Semester $semester, string $feeType = 'HP'): array
{
    return [
        'student_ids' => [$student->id],
        'dng_fee_type' => $feeType,
        'due_date' => now()->addDays(7)->toDateString(),
        'semester_id' => $semester->id,
        'description' => 'Batch canonical DNG',
        'estimate_time' => '07/26',
    ];
}

beforeEach(function (): void {
    $this->campus = Campus::factory()->create(['dng_code' => 'FAUHN']);
    $this->semester = Semester::factory()->create();
    $service = Mockery::mock(DngPaymentService::class);
    $service->shouldReceive('pushReserved')->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    app()->instance(DngPaymentService::class, $service);
    $resolver = Mockery::mock(DngCampusCodeResolver::class);
    $resolver->shouldReceive('requireForStudent')->andReturn('FAUHN');
    app()->instance(DngCampusCodeResolver::class, $resolver);
});

it('reserves selected HP lines with exact fee-family breakdowns', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);
    $tuition = batchCanonicalLine($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, '5000000.00');
    $egc = batchCanonicalLine($student, $this->semester, FinanceCharge::TYPE_EGC_LEVEL_FEE, '2000000.00');

    $result = app(CreateBatchDngFromChargesAction::class)->handle(batchDngPayload($student, $this->semester));
    $request = DngPaymentRequest::query()->sole();

    expect($result)->toMatchArray(['created' => 1, 'failed' => 0])
        ->and((float) $request->amount)->toBe(7_000_000.0)
        ->and($request->reservationTargets->pluck('invoice_line_id')->sort()->values()->all())->toBe([$tuition->id, $egc->id])
        ->and($request->chargeLinks->pluck('amount')->map(fn (string $amount): float => (float) $amount)->sum())->toBe(7_000_000.0);
});

it('caps a batch request at its pending installment rather than accepting a caller amount', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);
    $line = batchCanonicalLine($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, '10000000.00');
    $installment = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $line->charge_id,
        'installment_no' => 1,
        'amount' => 4_000_000,
        'due_date' => now()->addDays(7)->toDateString(),
    ]);

    app(CreateBatchDngFromChargesAction::class)->handle(batchDngPayload($student, $this->semester));
    $request = DngPaymentRequest::query()->sole();

    expect((float) $request->amount)->toBe(4_000_000.0)
        ->and($request->reservationTargets->sole()->finance_charge_installment_id)->toBe($installment->id)
        ->and($installment->fresh()->dng_payment_request_id)->toBe($request->id);
});

it('does not create a request when the selected fee family has no canonical payable line', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);

    $result = app(CreateBatchDngFromChargesAction::class)->handle(batchDngPayload($student, $this->semester));

    expect($result)->toMatchArray(['created' => 0, 'failed' => 1])
        ->and(DngPaymentRequest::query()->count())->toBe(0);
});
