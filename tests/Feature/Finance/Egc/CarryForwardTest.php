<?php

declare(strict_types=1);

use App\Models\CurriculumVersion;
use App\Models\Campus;
use App\Models\DiscountAllocation;
use App\Models\EgcBlock;
use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Modules\Finance\Actions\Egc\ApplyEgcCarryForwardAction;
use App\Modules\Finance\Queries\Egc\ListEgcCarryForwardCandidatesQuery;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function makeCarryForwardStudent(array $state = []): Student
{
    $intakeSemester = Semester::factory()->create();
    $cv = CurriculumVersion::factory()->state(['semester_id' => $intakeSemester->id])->create();

    return Student::factory()->state(array_merge([
        'curriculum_version_id' => $cv->id,
        'intake_semester_id' => $intakeSemester->id,
        'intake' => 1,
        'intake_mode' => 'sequential',
    ], $state))->create();
}

function makeEgcChargeWithInvoice(Student $student, Semester $semester, int $level): FinanceCharge
{
    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15_000_000,
        'description' => "EGC Level {$level} Fee",
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = StudentInvoice::firstOrCreate(
        ['student_id' => $student->id, 'semester_id' => $semester->id],
        [
            'invoice_number' => 'CF-'.rand(1000, 9999),
            'status' => 'draft',
            'due_date' => now()->addDays(30),
        ],
    );

    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $charge->amount,
        'description_snapshot' => $charge->description,
    ]);

    return $charge;
}

function makeCompletedPayment(Student $student, float $amount): Payment
{
    return Payment::create([
        'student_id' => $student->id,
        'amount' => $amount,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);
}

it('lists transitioned students with paid unused egc charge as eligible carry-forward candidates', function () {
    $semester = Semester::factory()->create();
    $student = makeCarryForwardStudent(['status' => 'intake_major']);

    $consumedCharge = makeEgcChargeWithInvoice($student, $semester, 1);
    $unusedCharge = makeEgcChargeWithInvoice($student, $semester, 2);

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 1,
        'finance_charge_id' => $consumedCharge->id,
        'result' => EgcBlock::RESULT_PASS,
    ])->create();

    $payment = makeCompletedPayment($student, 15_000_000);
    $line = InvoiceLine::query()->where('charge_id', $unusedCharge->id)->firstOrFail();
    app(SettlementService::class)->createPaymentApplication($payment, $line, 15_000_000, 'application');

    $groups = app(ListEgcCarryForwardCandidatesQuery::class)->handle($semester->id);
    $candidate = collect($groups['eligible'])->firstWhere('student_id', $student->id);

    expect($candidate)->not->toBeNull()
        ->and($candidate['eligible_release_amount'])->toBe(15_000_000.0)
        ->and($candidate['charged_levels_count'])->toBe(2)
        ->and($candidate['consumed_levels_count'])->toBe(1)
        ->and(collect($candidate['unused_charges'])->pluck('id')->all())->toBe([$unusedCharge->id]);
});

it('treats pending mapped egc blocks as unused for carry-forward eligibility', function () {
    $semester = Semester::factory()->create();
    $student = makeCarryForwardStudent(['status' => 'intake_course']);

    $charge1 = makeEgcChargeWithInvoice($student, $semester, 2);
    $charge2 = makeEgcChargeWithInvoice($student, $semester, 3);

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 2,
        'finance_charge_id' => $charge1->id,
        'result' => EgcBlock::RESULT_PASS,
    ])->create();

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 2,
        'level_number' => 3,
        'finance_charge_id' => $charge2->id,
        'result' => EgcBlock::RESULT_PENDING,
    ])->create();

    $payment = makeCompletedPayment($student, 15_000_000);
    $line = InvoiceLine::query()->where('charge_id', $charge2->id)->firstOrFail();
    app(SettlementService::class)->createPaymentApplication($payment, $line, 15_000_000, 'application');

    $candidate = collect(app(ListEgcCarryForwardCandidatesQuery::class)->handle($semester->id)['eligible'])
        ->firstWhere('student_id', $student->id);

    expect($candidate)->not->toBeNull()
        ->and($candidate['consumed_levels_count'])->toBe(1)
        ->and($candidate['eligible_release_amount'])->toBe(15_000_000.0)
        ->and(collect($candidate['unused_charges'])->pluck('id')->all())->toBe([$charge2->id]);
});

it('filters carry-forward candidates by campus', function () {
    $semester = Semester::factory()->create();
    $campusA = Campus::factory()->create();
    $campusB = Campus::factory()->create();

    $visibleStudent = makeCarryForwardStudent([
        'status' => 'intake_major',
        'campus_id' => $campusA->id,
    ]);
    $hiddenStudent = makeCarryForwardStudent([
        'status' => 'intake_major',
        'campus_id' => $campusB->id,
    ]);

    foreach ([$visibleStudent, $hiddenStudent] as $student) {
        $consumedCharge = makeEgcChargeWithInvoice($student, $semester, 1);
        $unusedCharge = makeEgcChargeWithInvoice($student, $semester, 2);

        EgcBlock::factory()->state([
            'student_id' => $student->id,
            'semester_id' => $semester->id,
            'block_number' => 1,
            'level_number' => 1,
            'finance_charge_id' => $consumedCharge->id,
            'result' => EgcBlock::RESULT_PASS,
        ])->create();

        $payment = makeCompletedPayment($student, 15_000_000);
        $line = InvoiceLine::query()->where('charge_id', $unusedCharge->id)->firstOrFail();
        app(SettlementService::class)->createPaymentApplication($payment, $line, 15_000_000, 'application');
    }

    $groups = app(ListEgcCarryForwardCandidatesQuery::class)->handle($semester->id, $campusA->id);

    expect(collect($groups['eligible'])->pluck('student_id')->all())->toBe([$visibleStudent->id]);
});

it('marks candidate as needs data repair when unused charge carries discount allocations', function () {
    $semester = Semester::factory()->create();
    $student = makeCarryForwardStudent(['status' => 'intake_major']);

    $consumedCharge = makeEgcChargeWithInvoice($student, $semester, 1);
    $unusedCharge = makeEgcChargeWithInvoice($student, $semester, 2);

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 1,
        'finance_charge_id' => $consumedCharge->id,
    ])->create();

    $invoice = StudentInvoice::query()->where('student_id', $student->id)->where('semester_id', $semester->id)->firstOrFail();
    $discount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'amount' => 5_000_000,
        'status' => 'active',
        'description' => 'Voucher',
        'discount_source' => 'tests',
    ]);

    $line = InvoiceLine::query()->where('charge_id', $unusedCharge->id)->firstOrFail();
    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 5_000_000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'tests',
    ]);

    $groups = app(ListEgcCarryForwardCandidatesQuery::class)->handle($semester->id);
    $candidate = collect($groups['needs_data_repair'])->firstWhere('student_id', $student->id);

    expect($candidate)->not->toBeNull()
        ->and($candidate['issues'][0])->toContain("Charge #{$unusedCharge->id}");
});

it('applies carry-forward by voiding unused egc charges without auto-reallocation', function () {
    $semester = Semester::factory()->create();
    $nextSemester = Semester::factory()->create();
    $student = makeCarryForwardStudent(['status' => 'intake_major']);
    $user = User::factory()->create();

    $consumedCharge = makeEgcChargeWithInvoice($student, $semester, 1);
    $unusedCharge = makeEgcChargeWithInvoice($student, $semester, 2);
    $futureTuitionCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $nextSemester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 20_000_000,
        'description' => 'Term 1 Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $futureInvoice = StudentInvoice::create([
        'invoice_number' => 'NEXT-'.rand(1000, 9999),
        'student_id' => $student->id,
        'semester_id' => $nextSemester->id,
        'status' => 'draft',
        'due_date' => now()->addDays(45),
    ]);

    InvoiceLine::create([
        'invoice_id' => $futureInvoice->id,
        'charge_id' => $futureTuitionCharge->id,
        'amount_snapshot' => $futureTuitionCharge->amount,
        'description_snapshot' => $futureTuitionCharge->description,
    ]);

    EgcBlock::factory()->state([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'block_number' => 1,
        'level_number' => 1,
        'finance_charge_id' => $consumedCharge->id,
    ])->create();

    $payment = makeCompletedPayment($student, 15_000_000);
    $unusedLine = InvoiceLine::query()->where('charge_id', $unusedCharge->id)->firstOrFail();
    app(SettlementService::class)->createPaymentApplication($payment, $unusedLine, 15_000_000, 'application', $user->id);

    $result = app(ApplyEgcCarryForwardAction::class)->run($student->id, $semester->id, $user->id);

    expect($result['released_amount'])->toBe(15_000_000.0)
        ->and(FinanceCharge::query()->find($unusedCharge->id)?->status)->toBe(FinanceCharge::STATUS_VOID)
        ->and($payment->fresh()->unapplied_amount)->toBe(15_000_000.0)
        ->and(PaymentApplication::query()->where('invoice_line_id', $unusedLine->id)->count())->toBe(2)
        ->and(PaymentApplication::query()->where('invoice_line_id', $unusedLine->id)->where('entry_type', 'reversal')->exists())->toBeTrue()
        ->and(PaymentApplication::query()->where('invoice_line_id', InvoiceLine::query()->where('charge_id', $futureTuitionCharge->id)->value('id'))->count())->toBe(0);
});

it('rejects carry-forward when student is still active egc', function () {
    $semester = Semester::factory()->create();
    $student = makeCarryForwardStudent(['status' => 'intake_pre_uni_gc']);

    makeEgcChargeWithInvoice($student, $semester, 1);

    expect(fn () => app(ApplyEgcCarryForwardAction::class)->run($student->id, $semester->id))
        ->toThrow(ValidationException::class);
});
