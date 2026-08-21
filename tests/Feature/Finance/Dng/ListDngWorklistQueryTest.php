<?php

declare(strict_types=1);

use App\Models\AcademicRecord;
use App\Models\Campus;
use App\Models\CourseOffering;
use App\Models\CourseRetakeRegistration;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Batch\AssembleBatchDngPreviewQuery;
use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

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
            'student_id' => $code,
            'full_name' => "Student {$code}",
            'curriculum_version_id' => $cv->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
}

function makeActiveCharge(Student $student, Semester $semester, string $chargeType, float $amount): FinanceCharge
{
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'test',
        'source_kind' => 'dng_worklist',
        'source_ref' => "dng-worklist:{$student->id}:{$chargeType}:".uniqid('', true),
        'obligation_type' => $chargeType,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'finance_obligation_id' => $obligation->id,
        'charge_type' => $chargeType,
        'amount' => $amount,
        'description' => "Charge {$chargeType}",
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    attachInvoiceLine(makeInvoice($student, $semester), $charge, $amount);

    return $charge;
}

function attachInvoiceLine(StudentInvoice $invoice, FinanceCharge $charge, float $amount): InvoiceLine
{
    return InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => $charge->description,
        'status' => 'active',
    ]);
}

function makeInvoice(Student $student, Semester $semester): StudentInvoice
{
    return StudentInvoice::create([
        'invoice_number' => 'INV-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
    ]);
}

function applyPayment(InvoiceLine $line, float $amount): void
{
    $payment = Payment::create([
        'student_id' => FinanceCharge::find($line->charge_id)->student_id,
        'amount' => $amount,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => $amount,
        'entry_type' => 'application',
        'applied_at' => now(),
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

/** @return Collection<int, BatchPreviewLine> */
function runDngPreviewLines(int $semesterId, string $dngFeeType): Collection
{
    return collect(app(AssembleBatchDngPreviewQuery::class)->handle($semesterId, $dngFeeType, [], null)['lines']);
}

// ─── Tests ──────────────────────────────────────────────────────────────────

beforeEach(function () {
    $this->campus = Campus::factory()->withDngMapping('FAUHN')->create();
    $this->semester = Semester::factory()->active()->create();

    app()->singleton('campus', fn () => $this->campus);
});

it('returns students with active HP charges and positive balance', function () {
    $student = makeWorklistStudent('HP001', $this->campus, $this->semester);
    makeActiveCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 10_000_000);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $students = collect($result['students']->items());

    expect($students)->toHaveCount(1);
    $row = $students->first();
    expect($row['student_code'])->toBe('HP001')
        ->and($row['balance'])->toBe(10_000_000.0)
        ->and($row['charge_count'])->toBe(1);
});

it('excludes students whose charges are fully paid', function () {
    $student = makeWorklistStudent('PAID001', $this->campus, $this->semester);
    $charge = makeActiveCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();
    applyPayment($line, 5_000_000);

    $result = runWorklist(['dng_fee_type' => 'HP']);

    expect($result['students']->total())->toBe(0);
});

it('returns the canonical remaining balance after a partial payment', function () {
    $student = makeWorklistStudent('BAL001', $this->campus, $this->semester);
    $charge = makeActiveCharge($student, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 10_000_000);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    // Apply partial payment
    applyPayment($line, 3_000_000);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $row = collect($result['students']->items())->first();

    expect((float) $row['balance'])->toBe(7_000_000.0)
        ->and((float) $row['total_paid'])->toBe(3_000_000.0);
});

it('excludes voided charges', function () {
    $student = makeWorklistStudent('VOID001', $this->campus, $this->semester);
    FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5_000_000,
        'description' => 'Voided charge',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_VOID,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP']);

    expect($result['students']->total())->toBe(0);
});

it('maps fee_type HP to tuition_term and egc_level_fee only', function () {
    $s = makeWorklistStudent('HP-MAP', $this->campus, $this->semester);

    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 1_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EGC_LEVEL_FEE, 2_000_000);
    // retake_fee should NOT be included under HP
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_RETAKE_FEE, 500_000);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $row = collect($result['students']->items())->first();

    expect($row['charge_count'])->toBe(2)
        ->and($row['balance'])->toBe(3_000_000.0);
});

it('maps fee_type HL to retake_fee only', function () {
    $s = makeWorklistStudent('HL-MAP', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_RETAKE_FEE, 1_500_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000); // not included

    $result = runWorklist(['dng_fee_type' => 'HL']);
    $row = collect($result['students']->items())->first();

    expect($row['charge_count'])->toBe(1)
        ->and($row['balance'])->toBe(1_500_000.0);
});

it('surfaces approved retake registrations without charges in the HL worklist', function () {
    $student = makeWorklistStudent('HL-SOURCE', $this->campus, $this->semester);
    $courseOffering = CourseOffering::factory()->create([
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
    ]);
    $record = AcademicRecord::factory()->create([
        'student_id' => $student->id,
        'campus_id' => $this->campus->id,
        'semester_id' => $this->semester->id,
        'unit_id' => $courseOffering->unit_id,
        'course_offering_id' => $courseOffering->id,
        'completion_status' => 'failed',
        'grade_status' => 'final',
        'is_passed' => false,
    ]);

    $registration = CourseRetakeRegistration::create([
        'student_id' => $student->id,
        'unit_id' => $courseOffering->unit_id,
        'original_academic_record_id' => $record->id,
        'course_offering_id' => null,
        'semester_id' => $this->semester->id,
        'campus_id' => $this->campus->id,
        'status' => CourseRetakeRegistration::STATUS_APPROVED,
        'attempt_number' => 2,
        'retake_fee' => 1_500_000,
        'approved_by_user_id' => User::factory()->create()->id,
        'approved_at' => now(),
    ]);

    $result = runWorklist(['dng_fee_type' => 'HL']);
    $row = collect($result['exceptions'])->first();

    expect($row['student_code'])->toBe('HL-SOURCE');
    expect($row['charge_count'])->toBe(0);
    expect($row['balance'])->toBeNull();
    expect($row['needs_review'])->toBeTrue();
    expect($row['settlement_issues'][0]['code'])->toBe('settlement_position.missing_payable_line');
});

it('maps fee_type PTL to exam_resit_fee only', function () {
    $s = makeWorklistStudent('PTL-MAP', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EXAM_RESIT_FEE, 750_000);

    $result = runWorklist(['dng_fee_type' => 'PTL']);
    $row = collect($result['students']->items())->first();

    expect($row['charge_count'])->toBe(1)
        ->and($row['balance'])->toBe(750_000.0);
});

it('filters by dng_status: no_dng returns students without active DNG', function () {
    $sWithDng = makeWorklistStudent('WITH-DNG', $this->campus, $this->semester);
    $sWithoutDng = makeWorklistStudent('WITHOUT-DNG', $this->campus, $this->semester);

    makeActiveCharge($sWithDng, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    makeActiveCharge($sWithoutDng, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);

    DngPaymentRequest::create([
        'student_id' => $sWithDng->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => 'WITH-DNG',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-01',
        'amount' => 5_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP', 'dng_status' => 'no_dng']);
    $students = collect($result['students']->items());

    expect($students->pluck('student_code')->toArray())->toContain('WITHOUT-DNG')
        ->and($students->pluck('student_code')->toArray())->not->toContain('WITH-DNG');
});

it('filters by dng_status: has_active_dng returns only students with pending/pushed DNG', function () {
    $sWithDng = makeWorklistStudent('HAS-DNG', $this->campus, $this->semester);
    $sWithoutDng = makeWorklistStudent('NO-DNG2', $this->campus, $this->semester);

    makeActiveCharge($sWithDng, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    makeActiveCharge($sWithoutDng, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);

    DngPaymentRequest::create([
        'student_id' => $sWithDng->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => 'HAS-DNG',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-02',
        'amount' => 5_000_000,
        'status' => DngPaymentRequest::STATUS_PENDING,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP', 'dng_status' => 'has_active_dng']);
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

    $result = runWorklist(['dng_fee_type' => 'HP', 'search' => 'SRCH001']);
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
        'student_id' => $s1->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => 'SUM001',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-SUM',
        'amount' => 10_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $summary = $result['summary'];

    expect($summary['total_students'])->toBe(2)
        ->and($summary['students_with_active_dng'])->toBe(1)
        ->and($summary['students_without_dng'])->toBe(1)
        ->and((float) $summary['total_balance'])->toBe(15_000_000.0);
});

it('includes individual charge breakdown in expanded rows', function () {
    $s = makeWorklistStudent('EXPAND001', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EGC_LEVEL_FEE, 3_000_000);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $row = collect($result['students']->items())->first();

    expect($row['charges'])->toHaveCount(2);
    $types = collect($row['charges'])->pluck('charge_type')->toArray();
    expect($types)->toContain(FinanceCharge::TYPE_TUITION_TERM)
        ->and($types)->toContain(FinanceCharge::TYPE_EGC_LEVEL_FEE);
});

it('attaches active_dng info to matching student', function () {
    $s = makeWorklistStudent('DNGINFO', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 8_000_000);

    $dng = DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'student_code' => 'DNGINFO',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-INFO',
        'amount' => 8_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $row = collect($result['students']->items())->first();

    expect($row['active_dng'])->not->toBeNull()
        ->and($row['active_dng']['id'])->toBe($dng->id)
        ->and($row['active_dng']['status'])->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG);
});

// ─── Phase 3: uncovered payable / four-way batch diff ─────────────────────

it('computes uncovered_amount for a partially-covered live DNG request (AUH15442 shape)', function () {
    config(['finance.dng.auto_replace_stale_collection' => true]);
    $s = makeWorklistStudent('UNCOV-PARTIAL', $this->campus, $this->semester);
    $chargeA = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EXAM_RESIT_FEE, 3_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EXAM_RESIT_FEE, 3_000_000);
    $lineA = InvoiceLine::query()->where('charge_id', $chargeA->id)->firstOrFail();

    $dng = DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-PARTIAL',
        'fee_type' => 'PTL',
        'item_id' => 'ITEM-UNCOV-PARTIAL',
        'amount' => 3_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    DngPaymentRequestReservationTarget::create([
        'dng_payment_request_id' => $dng->id,
        'invoice_line_id' => $lineA->id,
        'captured_collectible' => 3_000_000,
        'target_identity' => 'test-target-a',
    ]);

    $result = runWorklist(['dng_fee_type' => 'PTL', 'semester_id' => $this->semester->id]);
    $row = collect($result['students']->items())->first();

    expect($row['balance'])->toBe(6_000_000.0)
        ->and($row['coverage_known'])->toBeTrue()
        ->and($row['uncovered_amount'])->toBe(3_000_000.0);

    $line = runDngPreviewLines($this->semester->id, 'PTL')->firstWhere('key', "dng:student:{$s->id}:fee:PTL");
    expect($line->display['diff'])->toBe('update')
        ->and($line->display['uncovered_amount'])->toBe(3_000_000.0)
        ->and($line->display['reason'])->toBe('active_dng_replacement_required');
});

it('marks a fully-covered live DNG request as zero uncovered and diff skip', function () {
    $s = makeWorklistStudent('UNCOV-FULL', $this->campus, $this->semester);
    $charge = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    $dng = DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-FULL',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-FULL',
        'amount' => 5_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    DngPaymentRequestReservationTarget::create([
        'dng_payment_request_id' => $dng->id,
        'invoice_line_id' => $line->id,
        'captured_collectible' => 5_000_000,
        'target_identity' => 'test-target-full',
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP', 'semester_id' => $this->semester->id]);
    $row = collect($result['students']->items())->first();

    expect($row['coverage_known'])->toBeTrue()
        ->and($row['uncovered_amount'])->toBe(0.0);

    $previewLine = runDngPreviewLines($this->semester->id, 'HP')->firstWhere('key', "dng:student:{$s->id}:fee:HP");
    expect($previewLine->display['diff'])->toBe('skip')
        ->and($previewLine->display['reason'])->toBe('active_dng_already_covers_payable');
});

it('fails closed to coverage_known false and diff blocked when the live request has no reservation targets', function () {
    $s = makeWorklistStudent('UNCOV-LEGACY', $this->campus, $this->semester);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 4_000_000);

    DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-LEGACY',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-LEGACY',
        'amount' => 4_000_000,
        'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP', 'semester_id' => $this->semester->id]);
    $row = collect($result['students']->items())->first();

    expect($row['coverage_known'])->toBeFalse()
        ->and($row['uncovered_amount'])->toBeNull();

    $previewLine = runDngPreviewLines($this->semester->id, 'HP')->firstWhere('key', "dng:student:{$s->id}:fee:HP");
    expect($previewLine->display['diff'])->toBe('warning')
        ->and($previewLine->display['reason'])->toBe('active_dng_coverage_unknown');
});

it('blocks (never creates or replaces) when the live collection is in a different semester', function () {
    // reserve()'s own $existing lookup has no semester_id filter (active_slot_key
    // has no semester component — plan.md red-team C5), so a live collection in
    // semester A still holds the slot when pushing semester B. The worklist must
    // NOT read that as "create" (would attempt a second, uncoordinated push into
    // reserve() which is really about to hold-for-review or replace the OTHER
    // semester's request) nor silently subtract it from semester B's uncovered.
    config(['finance.dng.auto_replace_stale_collection' => true]);
    $semB = Semester::factory()->create();
    $s = makeWorklistStudent('UNCOV-XSEM', $this->campus, $this->semester);

    $chargeA = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);
    $lineA = InvoiceLine::query()->where('charge_id', $chargeA->id)->firstOrFail();
    $dngA = DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-XSEM',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-XSEM',
        'amount' => 3_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    DngPaymentRequestReservationTarget::create([
        'dng_payment_request_id' => $dngA->id,
        'invoice_line_id' => $lineA->id,
        'captured_collectible' => 3_000_000,
        'target_identity' => 'test-target-xsem',
    ]);

    // Fresh payable in a DIFFERENT semester, no live collection scoped to it.
    makeActiveCharge($s, $semB, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);

    $resultB = runWorklist(['dng_fee_type' => 'HP', 'semester_id' => $semB->id]);
    $rowB = collect($resultB['students']->items())->first();

    expect($rowB['active_dng'])->not->toBeNull()
        ->and($rowB['active_dng']['id'])->toBe($dngA->id)
        ->and($rowB['coverage_known'])->toBeFalse()
        ->and($rowB['uncovered_amount'])->toBeNull()
        ->and($rowB['balance'])->toBe(3_000_000.0);

    $previewLineB = runDngPreviewLines($semB->id, 'HP')->firstWhere('key', "dng:student:{$s->id}:fee:HP");
    expect($previewLineB->display['diff'])->toBe('warning')
        ->and($previewLineB->display['reason'])->toBe('active_dng_coverage_unknown');
});

it('does not compute uncovered_amount when no semester filter is applied (unscoped browse)', function () {
    $s = makeWorklistStudent('UNCOV-UNSCOPED', $this->campus, $this->semester);
    $charge = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 5_000_000);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    $dng = DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-UNSCOPED',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-UNSCOPED',
        'amount' => 2_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    DngPaymentRequestReservationTarget::create([
        'dng_payment_request_id' => $dng->id,
        'invoice_line_id' => $line->id,
        'captured_collectible' => 2_000_000,
        'target_identity' => 'test-target-unscoped',
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP']);
    $row = collect($result['students']->items())->first();

    expect($row['active_dng'])->not->toBeNull()
        ->and($row['coverage_known'])->toBeNull()
        ->and($row['uncovered_amount'])->toBeNull();
});

it('does not report a phantom gap for a live tranche-1 collection on a split installment plan', function () {
    // CRITICAL-1 regression: uncovered must compare against the
    // installment-capped next_push_amount (tranche 2's own amount, since
    // tranche 1 is already awaiting_payment and no longer "pending"), never
    // the raw semester remaining — otherwise a healthy tranche-1 collection
    // reads as short by the untouched, not-yet-due tranche 2 and a batch
    // commit would degrade it via a pointless cancel-then-push.
    config(['finance.dng.auto_replace_stale_collection' => true]);
    $s = makeWorklistStudent('UNCOV-SPLIT', $this->campus, $this->semester);
    $charge = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 20_000_000);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();

    $dng = DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-SPLIT',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-SPLIT',
        'amount' => 10_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    DngPaymentRequestReservationTarget::create([
        'dng_payment_request_id' => $dng->id,
        'invoice_line_id' => $line->id,
        'captured_collectible' => 10_000_000,
        'target_identity' => 'test-target-split',
    ]);
    FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => 10_000_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $dng->id,
    ]);
    FinanceChargeInstallment::create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 2,
        'amount' => 10_000_000,
        'due_date' => now()->addDays(60)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_PENDING,
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP', 'semester_id' => $this->semester->id]);
    $row = collect($result['students']->items())->first();

    expect($row['coverage_known'])->toBeTrue()
        ->and($row['uncovered_amount'])->toBe(0.0);

    $previewLine = runDngPreviewLines($this->semester->id, 'HP')->firstWhere('key', "dng:student:{$s->id}:fee:HP");
    expect($previewLine->display['diff'])->toBe('skip');
});

it('renders blocked, not replace, when auto-replace is disabled', function () {
    // CRITICAL-2 regression: the flag ships off (config/finance.php, plan.md
    // Rollback). DngReservationLifecycle::reserve() refuses to replace while
    // it is off and holds the request for review instead — offering
    // 'replace' here would be a promise the commit cannot keep.
    config(['finance.dng.auto_replace_stale_collection' => false]);
    $s = makeWorklistStudent('UNCOV-FLAGOFF', $this->campus, $this->semester);
    $chargeA = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);
    $lineA = InvoiceLine::query()->where('charge_id', $chargeA->id)->firstOrFail();

    DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-FLAGOFF',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-FLAGOFF',
        'amount' => 3_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ])->reservationTargets()->create([
        'invoice_line_id' => $lineA->id,
        'captured_collectible' => 3_000_000,
        'target_identity' => 'test-target-flagoff',
    ]);

    $previewLine = runDngPreviewLines($this->semester->id, 'HP')->firstWhere('key', "dng:student:{$s->id}:fee:HP");
    expect($previewLine->display['diff'])->toBe('warning')
        ->and($previewLine->display['reason'])->toBe('active_dng_replacement_disabled');
});

it('blocks a partially-covered live request stuck in needs_review even though it has reservation targets', function () {
    // HIGH-4: needs_review/unknown_outcome are explicitly out of scope for
    // automatic anything (plan.md Non-goals) — status alone must block,
    // independent of whether reservation targets exist (that is the
    // separate H14 case, covered above).
    config(['finance.dng.auto_replace_stale_collection' => true]);
    $s = makeWorklistStudent('UNCOV-REVIEW', $this->campus, $this->semester);
    $chargeA = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);
    $lineA = InvoiceLine::query()->where('charge_id', $chargeA->id)->firstOrFail();

    DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-REVIEW',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-REVIEW',
        'amount' => 3_000_000,
        'status' => DngPaymentRequest::STATUS_NEEDS_REVIEW,
    ])->reservationTargets()->create([
        'invoice_line_id' => $lineA->id,
        'captured_collectible' => 3_000_000,
        'target_identity' => 'test-target-review',
    ]);

    $result = runWorklist(['dng_fee_type' => 'HP', 'semester_id' => $this->semester->id]);
    $row = collect($result['students']->items())->first();

    expect($row['coverage_known'])->toBeFalse()
        ->and($row['uncovered_amount'])->toBeNull();

    $previewLine = runDngPreviewLines($this->semester->id, 'HP')->firstWhere('key', "dng:student:{$s->id}:fee:HP");
    expect($previewLine->display['diff'])->toBe('warning')
        ->and($previewLine->display['reason'])->toBe('active_dng_coverage_unknown');
});

it('blocks when a direct payment lands on a line the live request already claims (per-line superset guard)', function () {
    // HIGH-2 (code review): the aggregate uncovered>0 check alone is not
    // enough — DngReservationLifecycle::eligibleForAutomaticReplacement()
    // also requires every existing target's captured_collectible to stay
    // <= its own line's *current* remaining. A direct (non-DNG) payment on
    // that line breaks that per-line invariant while the aggregate still
    // shows a gap (from an unrelated new charge) — reserve() would refuse
    // to replace here and hold for review, so the preview must not offer
    // 'replace' either.
    config(['finance.dng.auto_replace_stale_collection' => true]);
    $s = makeWorklistStudent('UNCOV-PARTPAY', $this->campus, $this->semester);
    $chargeA = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 3_000_000);
    $lineA = InvoiceLine::query()->where('charge_id', $chargeA->id)->firstOrFail();

    DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-PARTPAY',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-PARTPAY',
        'amount' => 3_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ])->reservationTargets()->create([
        'invoice_line_id' => $lineA->id,
        'captured_collectible' => 3_000_000,
        'target_identity' => 'test-target-partpay',
    ]);

    // A direct payment reduces line A's remaining below what the live
    // request's target already claims for it.
    applyPayment($lineA, 1_000_000);

    $result = runWorklist(['dng_fee_type' => 'HP', 'semester_id' => $this->semester->id]);
    $row = collect($result['students']->items())->first();

    expect($row['coverage_known'])->toBeFalse()
        ->and($row['uncovered_amount'])->toBeNull();

    $previewLine = runDngPreviewLines($this->semester->id, 'HP')->firstWhere('key', "dng:student:{$s->id}:fee:HP");
    expect($previewLine->display['diff'])->toBe('warning')
        ->and($previewLine->display['reason'])->toBe('active_dng_coverage_unknown');
});

it('conservatively reads skip (not replace) when a new charge has no installment plan alongside a live tranche-1 request', function () {
    // MEDIUM-3 (code review, documented and pinned, not fixed): known
    // limitation inherited from next_push_amount's own pre-existing
    // aggregation, not introduced by this phase — $nextPushAmountByStudent
    // sums ONLY charges that have installment rows (INNER JOIN against
    // finance_charge_installments), so a same-semester charge with no
    // installment plan at all contributes nothing to next_push_amount and
    // is invisible to the uncovered/coverage math, even though
    // CreateBatchDngFromChargesAction::processStudent() WOULD include it at
    // its full collectible in a real push. The direction is conservative —
    // this can only produce a false 'skip' (a real gap hidden), never a
    // false 'replace' (never invents money that isn't there) — so it fails
    // safe, but it is a real accuracy gap worth fixing if this shape proves
    // common. Pinned here so a future incidental fix is a deliberate,
    // reviewed change instead of a silent behavior flip.
    config(['finance.dng.auto_replace_stale_collection' => true]);
    $s = makeWorklistStudent('UNCOV-MIXED', $this->campus, $this->semester);
    $chargeA = makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_TUITION_TERM, 20_000_000);
    $lineA = InvoiceLine::query()->where('charge_id', $chargeA->id)->firstOrFail();

    $dng = DngPaymentRequest::create([
        'student_id' => $s->id,
        'campus_code' => 'FAUHN',
        'provider_rail' => 'dng',
        'semester_id' => $this->semester->id,
        'student_code' => 'UNCOV-MIXED',
        'fee_type' => 'HP',
        'item_id' => 'ITEM-UNCOV-MIXED',
        'amount' => 10_000_000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);
    $dng->reservationTargets()->create([
        'invoice_line_id' => $lineA->id,
        'captured_collectible' => 10_000_000,
        'target_identity' => 'test-target-mixed-a',
    ]);
    FinanceChargeInstallment::create([
        'finance_charge_id' => $chargeA->id,
        'installment_no' => 1,
        'amount' => 10_000_000,
        'due_date' => now()->addDays(30)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $dng->id,
    ]);
    FinanceChargeInstallment::create([
        'finance_charge_id' => $chargeA->id,
        'installment_no' => 2,
        'amount' => 10_000_000,
        'due_date' => now()->addDays(60)->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_PENDING,
    ]);

    // New charge, same semester, NO installment plan at all.
    makeActiveCharge($s, $this->semester, FinanceCharge::TYPE_EGC_LEVEL_FEE, 6_000_000);

    $previewLine = runDngPreviewLines($this->semester->id, 'HP')->firstWhere('key', "dng:student:{$s->id}:fee:HP");

    expect($previewLine->display['diff'])->toBe('skip')
        ->and($previewLine->display['uncovered_amount'])->toBe(0.0);
});
