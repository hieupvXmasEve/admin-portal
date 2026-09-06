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
    $this->campus = Campus::factory()->withDngMapping('FAUHN')->create();
    $this->semester = Semester::factory()->create();
    $service = Mockery::mock(DngPaymentService::class)->shouldIgnoreMissing();
    $service->shouldReceive('pushReserved')->andReturn(['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []]);
    app()->instance(DngPaymentService::class, $service);
    $resolver = Mockery::mock(DngCampusCodeResolver::class);
    $resolver->shouldReceive('requireForCampusId')->andReturn('FAUHN');
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

it('reports target drift review instead of claiming a batch installment is awaiting payment', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);
    $line = batchCanonicalLine($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, '10000000.00');
    $installment = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $line->charge_id,
        'installment_no' => 1,
        'amount' => 4_000_000,
        'due_date' => now()->addDays(7)->toDateString(),
    ]);
    $service = Mockery::mock(DngPaymentService::class)->shouldIgnoreMissing();
    $service->shouldReceive('pushReserved')->once()->andReturnUsing(function () use ($line): array {
        $line->update(['amount_snapshot' => '3000000.00']);

        return ['Code' => 1, 'Type' => 'success', 'Message' => 'ok', 'data' => []];
    });
    app()->instance(DngPaymentService::class, $service);

    $result = app(CreateBatchDngFromChargesAction::class)->handle(batchDngPayload($student, $this->semester));
    $request = DngPaymentRequest::query()->sole();

    expect($result['errors'])->toBe([])
        ->and($result)->toMatchArray(['created' => 0, 'needs_review' => 1, 'failed' => 0])
        ->and($request->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW)
        ->and($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING)
        ->and($result['outcomes'])->toBe([[
            'student_id' => $student->id,
            'reservation_id' => $request->id,
            'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
        ]]);
});

it('reports an ambiguous provider outcome without retrying the batch installment', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);
    $line = batchCanonicalLine($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, '10000000.00');
    $installment = FinanceChargeInstallment::factory()->create([
        'finance_charge_id' => $line->charge_id,
        'installment_no' => 1,
        'amount' => 4_000_000,
        'due_date' => now()->addDays(7)->toDateString(),
    ]);
    $service = Mockery::mock(DngPaymentService::class)->shouldIgnoreMissing();
    $service->shouldReceive('pushReserved')->once()->andThrow(new RuntimeException('Connection timeout'));
    app()->instance(DngPaymentService::class, $service);

    $result = app(CreateBatchDngFromChargesAction::class)->handle(batchDngPayload($student, $this->semester));
    $request = DngPaymentRequest::query()->sole();

    expect($result)->toMatchArray(['created' => 0, 'needs_review' => 1, 'failed' => 0, 'errors' => []])
        ->and($request->status)->toBe(DngPaymentRequest::STATUS_UNKNOWN_OUTCOME)
        ->and($installment->fresh()->status)->toBe(FinanceChargeInstallment::STATUS_PENDING)
        ->and($installment->fresh()->dng_payment_request_id)->toBe($request->id)
        ->and($result['outcomes'])->toBe([[
            'student_id' => $student->id,
            'reservation_id' => $request->id,
            'status' => DngPaymentRequest::STATUS_UNKNOWN_OUTCOME,
        ]]);
});

it('does not create a request when the selected fee family has no canonical payable line', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);

    $result = app(CreateBatchDngFromChargesAction::class)->handle(batchDngPayload($student, $this->semester));

    expect($result)->toMatchArray(['created' => 0, 'failed' => 1])
        ->and(DngPaymentRequest::query()->count())->toBe(0);
});

it('does not report an older held reservation as the outcome of a later failed batch attempt', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);
    $line = batchCanonicalLine($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, '10000000.00');
    $action = app(CreateBatchDngFromChargesAction::class);
    $action->handle(batchDngPayload($student, $this->semester));
    $existing = DngPaymentRequest::query()->sole();
    $existing->update(['status' => DngPaymentRequest::STATUS_NEEDS_REVIEW]);
    $line->update(['status' => 'void']);

    $result = $action->handle(batchDngPayload($student, $this->semester));

    expect($result)->toMatchArray(['created' => 0, 'needs_review' => 0, 'failed' => 1, 'outcomes' => []])
        ->and($existing->fresh()->status)->toBe(DngPaymentRequest::STATUS_NEEDS_REVIEW);
});

it('writes the staff-chosen due date onto an existing invoice', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);
    $line = batchCanonicalLine($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, '5000000.00');
    $invoice = StudentInvoice::query()->findOrFail($line->invoice_id);
    $originalDueDate = $invoice->due_date?->toDateString();
    $chosenDueDate = now()->addDays(21)->toDateString();

    expect($originalDueDate)->not->toBe($chosenDueDate);

    $payload = batchDngPayload($student, $this->semester);
    $payload['due_date'] = $chosenDueDate;

    $result = app(CreateBatchDngFromChargesAction::class)->handle($payload);

    expect($result)->toMatchArray(['created' => 1, 'failed' => 0])
        ->and($invoice->fresh()->due_date?->toDateString())->toBe($chosenDueDate);
});

it('lets the latest DNG commit win when two fee types share one invoice', function (): void {
    $student = batchDngStudent($this->campus, $this->semester);
    $tuition = batchCanonicalLine($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, '5000000.00');
    $invoice = StudentInvoice::query()->findOrFail($tuition->invoice_id);
    $bhytCharge = FinanceCharge::query()->create([
        'finance_obligation_id' => FinanceObligation::query()->create([
            'billing_account_id' => BillingAccount::query()->where('student_id', $student->id)->sole()->id,
            'source_system' => 'finance-test',
            'source_kind' => 'batch_dng',
            'source_ref' => uniqid('batch:', true),
            'obligation_type' => FinanceCharge::TYPE_BHYT,
            'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
            'amount' => '564000.00',
            'currency' => 'VND',
            'pricing_rule_version' => 'test',
            'pricing_snapshot' => [],
            'accepted_at' => now(),
        ])->id,
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_BHYT,
        'amount' => '564000.00',
        'description' => 'BHYT on shared invoice',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $bhytCharge->id,
        'amount_snapshot' => '564000.00',
        'description_snapshot' => 'BHYT on shared invoice',
        'status' => 'active',
    ]);

    $hpDate = now()->addDays(14)->toDateString();
    $bhytDate = now()->addDays(28)->toDateString();

    $hp = batchDngPayload($student, $this->semester, 'HP');
    $hp['due_date'] = $hpDate;
    expect(app(CreateBatchDngFromChargesAction::class)->handle($hp))->toMatchArray(['created' => 1, 'failed' => 0])
        ->and($invoice->fresh()->due_date?->toDateString())->toBe($hpDate);

    $bhyt = batchDngPayload($student, $this->semester, 'BHYT');
    $bhyt['due_date'] = $bhytDate;
    expect(app(CreateBatchDngFromChargesAction::class)->handle($bhyt))->toMatchArray(['created' => 1, 'failed' => 0])
        ->and($invoice->fresh()->due_date?->toDateString())->toBe($bhytDate);
});
