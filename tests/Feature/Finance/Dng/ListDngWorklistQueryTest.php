<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\DiscountAllocation;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

// ─── Helpers ────────────────────────────────────────────────────────────────

function makeWorklistStudent(string $code, Campus $campus, Semester $semester): Student
{
    $program = Program::factory()->create();
    $cv = CurriculumVersion::factory()->forProgram($program)->withEffectiveSemester($semester)->create();

    return Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id'           => $code,
            'full_name'            => "Student {$code}",
            'curriculum_version_id' => $cv->id,
            'intake_semester_id'   => $semester->id,
            'intake'               => 1,
            'intake_mode'          => 'sequential',
        ])
        ->create();
}

function makeActiveCharge(Student $student, Semester $semester, string $chargeType, float $amount): FinanceCharge
{
    return FinanceCharge::create([
        'student_id'  => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => $chargeType,
        'amount'      => $amount,
        'description' => "Charge {$chargeType}",
        'effective_at' => now(),
        'status'      => FinanceCharge::STATUS_ACTIVE,
    ]);
}

function attachInvoiceLine(StudentInvoice $invoice, FinanceCharge $charge, float $amount): InvoiceLine
{
    return InvoiceLine::create([
        'invoice_id'           => $invoice->id,
        'charge_id'            => $charge->id,
        'amount_snapshot'      => $amount,
        'description_snapshot' => $charge->description,
        'status'               => 'active',
    ]);
}

function makeInvoice(Student $student, Semester $semester): StudentInvoice
{
    return StudentInvoice::create([
        'invoice_number' => 'INV-' . uniqid(),
        'student_id'     => $student->id,
        'semester_id'    => $semester->id,
        'status'         => 'pending',
        'due_date'       => now()->addDays(30)->toDateString(),
        'subtotal'       => 0,
        'discount_total' => 0,
        'total_amount'   => 0,
        'paid_amount'    => 0,
    ]);
}

function applyPayment(InvoiceLine $line, float $amount): void
{
    $payment = Payment::create([
        'student_id' => FinanceCharge::find($line->charge_id)->student_id,
        'amount'     => $amount,
        'method'     => Payment::METHOD_IMPORT,
        'source'     => 'import',
        'paid_at'    => now(),
        'status'     => Payment::STATUS_COMPLETED,
    ]);

    PaymentApplication::create([
        'payment_id'     => $payment->id,
        'invoice_line_id' => $line->id,
        'amount'         => $amount,
        'entry_type'     => 'application',
        'applied_at'     => now(),
    ]);
}

function runWorklist(array $params = []): array
{
    $request = Request::create('/finance/operations/dng-worklist', 'GET', array_merge(
        ['dng_fee_type' => 'HP'],
        $params,
    ));

    return app(ListDngWorklistQuery::class)->handle($request);
}

// ─── Tests ──────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->campus   = Campus::factory()->create(['dng_code' => 'FAUHN']);
    $this->semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $this->campus);
});

it('returns students with active HP charges and positive balance', function () {
    $student = makeWorklistStudent('HP001', $this->campus, $this->semester);
    makeActiveCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 10_000_000);

    $result  = runWorklist(['dng_fee_type' => 'HP']);
    $students = collect($result['students']->items());

    expect($students)->toHaveCount(1);
    $row = $students->first();
    expect($row['student_code'])->toBe('HP001')
        ->and($row['balance'])->toBe(10_000_000.0)
        ->and($row['charge_count'])->toBe(1);
});

it('excludes students whose charges are fully paid', function () {
    $student = makeWorklistStudent('PAID001', $this->campus, $this->semester);
    $charge  = makeActiveCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    $invoice = makeInvoice($student, $this->semester);
    $line    = attachInvoiceLine($invoice, $charge, 5_000_000);
    applyPayment($line, 5_000_000);

    $result = runWorklist(['dng_fee_type' => 'HP']);

    expect($result['students']->total())->toBe(0);
});

it('calculates balance correctly: amount minus paid and discount', function () {
    $student = makeWorklistStudent('BAL001', $this->campus, $this->semester);
    $charge  = makeActiveCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 10_000_000);
    $invoice = makeInvoice($student, $this->semester);
    $line    = attachInvoiceLine($invoice, $charge, 10_000_000);

    // Apply partial payment
    applyPayment($line, 3_000_000);

    $result  = runWorklist(['dng_fee_type' => 'HP']);
    $row     = collect($result['students']->items())->first();

    expect((float) $row['balance'])->toBe(7_000_000.0)
        ->and((float) $row['total_paid'])->toBe(3_000_000.0);
});

it('excludes voided charges', function () {
    $student = makeWorklistStudent('VOID001', $this->campus, $this->semester);
    FinanceCharge::create([
        'student_id'  => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount'      => 5_000_000,
        'description' => 'Voided charge',
        'effective_at' => now(),
        'status'      => FinanceCharge::STATUS_VOID,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP']);

    expect($result['students']->total())->toBe(0);
});

it('maps fee_type HP to tuition_term, egc_level_fee, course_fee', function () {
    $s = makeWorklistStudent('HP-MAP', $this->campus, $this->semester);

    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM,  1_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EGC_LEVEL_FEE, 2_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_COURSE_FEE,    3_000_000);

    // retake_fee should NOT be included under HP
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_RETAKE_FEE, 500_000);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $row    = collect($result['students']->items())->first();

    expect($row['charge_count'])->toBe(3)
        ->and($row['balance'])->toBe(6_000_000.0);
});

it('maps fee_type HL to retake_fee only', function () {
    $s = makeWorklistStudent('HL-MAP', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_RETAKE_FEE, 1_500_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000); // not included

    $result = runWorklist(['dng_fee_type' => 'HL']);
    $row    = collect($result['students']->items())->first();

    expect($row['charge_count'])->toBe(1)
        ->and($row['balance'])->toBe(1_500_000.0);
});

it('maps fee_type PTL to exam_resit_fee only', function () {
    $s = makeWorklistStudent('PTL-MAP', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EXAM_RESIT_FEE, 750_000);

    $result = runWorklist(['dng_fee_type' => 'PTL']);
    $row    = collect($result['students']->items())->first();

    expect($row['charge_count'])->toBe(1)
        ->and($row['balance'])->toBe(750_000.0);
});

it('filters by dng_status: no_dng returns students without active DNG', function () {
    $sWithDng    = makeWorklistStudent('WITH-DNG', $this->campus, $this->semester);
    $sWithoutDng = makeWorklistStudent('WITHOUT-DNG', $this->campus, $this->semester);

    makeActiveCharge($sWithDng,    $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    makeActiveCharge($sWithoutDng, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);

    DngPaymentRequest::create([
        'student_id'   => $sWithDng->id,
        'campus_code'  => 'FAUHN',
        'student_code' => 'WITH-DNG',
        'fee_type'     => 'HP',
        'item_id'      => 'ITEM-01',
        'amount'       => 5_000_000,
        'status'       => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $result   = runWorklist(['dng_fee_type' => 'HP', 'dng_status' => 'no_dng']);
    $students = collect($result['students']->items());

    expect($students->pluck('student_code')->toArray())->toContain('WITHOUT-DNG')
        ->and($students->pluck('student_code')->toArray())->not->toContain('WITH-DNG');
});

it('filters by dng_status: has_active_dng returns only students with pending/pushed DNG', function () {
    $sWithDng    = makeWorklistStudent('HAS-DNG', $this->campus, $this->semester);
    $sWithoutDng = makeWorklistStudent('NO-DNG2', $this->campus, $this->semester);

    makeActiveCharge($sWithDng,    $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    makeActiveCharge($sWithoutDng, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);

    DngPaymentRequest::create([
        'student_id'   => $sWithDng->id,
        'campus_code'  => 'FAUHN',
        'student_code' => 'HAS-DNG',
        'fee_type'     => 'HP',
        'item_id'      => 'ITEM-02',
        'amount'       => 5_000_000,
        'status'       => DngPaymentRequest::STATUS_PENDING,
    ]);

    $result   = runWorklist(['dng_fee_type' => 'HP', 'dng_status' => 'has_active_dng']);
    $students = collect($result['students']->items());

    expect($students->pluck('student_code')->toArray())->toContain('HAS-DNG')
        ->and($students->pluck('student_code')->toArray())->not->toContain('NO-DNG2');
});

it('filters by semester_id', function () {
    $semA = $this->semester;
    $semB = Semester::factory()->create();

    $s = makeWorklistStudent('SEM-FILTER', $this->campus, $this->semester);
    makeActiveCharge($s, $semA, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    makeActiveCharge($s, $semB, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);

    $resultA = runWorklist(['dng_fee_type' => 'HP', 'semester_id' => $semA->id]);
    $resultB = runWorklist(['dng_fee_type' => 'HP', 'semester_id' => $semB->id]);

    $rowA = collect($resultA['students']->items())->first();
    $rowB = collect($resultB['students']->items())->first();

    expect((float) $rowA['balance'])->toBe(5_000_000.0)
        ->and((float) $rowB['balance'])->toBe(3_000_000.0);
});

it('filters by search (student name or code)', function () {
    $sA = makeWorklistStudent('SRCH001', $this->campus, $this->semester);
    $sB = makeWorklistStudent('SRCH002', $this->campus, $this->semester);

    makeActiveCharge($sA, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    makeActiveCharge($sB, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);

    $result   = runWorklist(['dng_fee_type' => 'HP', 'search' => 'SRCH001']);
    $students = collect($result['students']->items());

    expect($students)->toHaveCount(1)
        ->and($students->first()['student_code'])->toBe('SRCH001');
});

it('returns correct summary counts', function () {
    $s1 = makeWorklistStudent('SUM001', $this->campus, $this->semester);
    $s2 = makeWorklistStudent('SUM002', $this->campus, $this->semester);

    makeActiveCharge($s1, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 10_000_000);
    makeActiveCharge($s2, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);

    DngPaymentRequest::create([
        'student_id'   => $s1->id,
        'campus_code'  => 'FAUHN',
        'student_code' => 'SUM001',
        'fee_type'     => 'HP',
        'item_id'      => 'ITEM-SUM',
        'amount'       => 10_000_000,
        'status'       => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $result  = runWorklist(['dng_fee_type' => 'HP']);
    $summary = $result['summary'];

    expect($summary['total_students'])->toBe(2)
        ->and($summary['students_with_active_dng'])->toBe(1)
        ->and($summary['students_without_dng'])->toBe(1)
        ->and((float) $summary['total_balance'])->toBe(15_000_000.0);
});

it('includes individual charge breakdown in expanded rows', function () {
    $s = makeWorklistStudent('EXPAND001', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM,  5_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EGC_LEVEL_FEE, 3_000_000);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $row    = collect($result['students']->items())->first();

    expect($row['charges'])->toHaveCount(2);
    $types = collect($row['charges'])->pluck('charge_type')->toArray();
    expect($types)->toContain(FinanceCharge::TYPE_TUITION_TERM)
        ->and($types)->toContain(FinanceCharge::TYPE_EGC_LEVEL_FEE);
});

it('attaches active_dng info to matching student', function () {
    $s = makeWorklistStudent('DNGINFO', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 8_000_000);

    $dng = DngPaymentRequest::create([
        'student_id'   => $s->id,
        'campus_code'  => 'FAUHN',
        'student_code' => 'DNGINFO',
        'fee_type'     => 'HP',
        'item_id'      => 'ITEM-INFO',
        'amount'       => 8_000_000,
        'status'       => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $row    = collect($result['students']->items())->first();

    expect($row['active_dng'])->not->toBeNull()
        ->and($row['active_dng']['id'])->toBe($dng->id)
        ->and($row['active_dng']['status'])->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});
