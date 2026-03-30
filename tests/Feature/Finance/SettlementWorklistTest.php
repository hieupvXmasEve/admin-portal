<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Queries\Operations\ListSettlementWorklistQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

function createSettlementStudent(string $studentCode, ?Campus $campus = null): array
{
    $campus ??= Campus::factory()->create();
    $program = Program::factory()->create();
    $semester = Semester::factory()->active()->create();
    $curriculumVersion = CurriculumVersion::factory()
        ->forProgram($program)
        ->withEffectiveSemester($semester)
        ->create();

    $student = Student::factory()
        ->forCampus($campus)
        ->forProgram($program)
        ->state([
            'student_id' => $studentCode,
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    return [$student, $semester, $campus];
}

function createUnpaidInvoice(Student $student, Semester $semester, string $invoiceNumber, float $amount): array
{
    $invoice = StudentInvoice::query()->create([
        'invoice_number' => $invoiceNumber,
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
    ]);

    $charge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => $amount,
        'description' => 'EGC Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $line = InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'EGC Fee',
        'status' => 'active',
    ]);

    return [$invoice, $charge, $line];
}

it('lists unpaid students with readiness based on unapplied cash', function () {
    [$studentReady, $semesterReady, $campus] = createSettlementStudent('AUS-READY');
    [$studentNoCash, $semesterNoCash] = createSettlementStudent('AUS-NOCASH', $campus);

    app()->singleton('campus', fn () => $campus);

    createUnpaidInvoice($studentReady, $semesterReady, 'INV-READY', 15000000);
    createUnpaidInvoice($studentNoCash, $semesterNoCash, 'INV-NOCASH', 12000000);

    Payment::query()->create([
        'student_id' => $studentReady->id,
        'amount' => 10000000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $result = app(ListSettlementWorklistQuery::class)->handle(Request::create('/finance/operations/settlement', 'GET'));
    $rows = collect($result['students']->items());

    expect($result['summary']['students_with_unpaid_invoices'])->toBe(2)
        ->and($result['summary']['ready_students'])->toBe(1)
        ->and($rows->firstWhere('student_code', 'AUS-READY')['actionable'])->toBeTrue()
        ->and($rows->firstWhere('student_code', 'AUS-NOCASH')['actionable'])->toBeFalse();
});

it('includes the latest dng request indicator for each student row', function () {
    [$student, $semester, $campus] = createSettlementStudent('AUS-DNG');

    app()->singleton('campus', fn () => $campus);

    createUnpaidInvoice($student, $semester, 'INV-DNG', 12000000);

    DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'description' => 'Outstanding tuition for settlement',
        'item_id' => 'ITEM-DNG-001',
        'amount' => 12000000,
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
        'dng_payment_id' => 'PAY-DNG-001',
    ]);

    $result = app(ListSettlementWorklistQuery::class)->handle(Request::create('/finance/operations/settlement', 'GET'));
    $row = collect($result['students']->items())->firstWhere('student_code', 'AUS-DNG');

    expect($row)->not->toBeNull()
        ->and($row['latest_dng_request'])->not->toBeNull()
        ->and($row['latest_dng_request']['status'])->toBe(DngPaymentRequest::STATUS_PUSHED_TO_DNG)
        ->and($row['latest_dng_request']['description'])->toBe('Outstanding tuition for settlement');
});

it('excludes invoice lines whose linked charges are void from active due totals', function () {
    [$student, $semester, $campus] = createSettlementStudent('AUS-VOIDED');

    app()->singleton('campus', fn () => $campus);

    [$invoice] = createUnpaidInvoice($student, $semester, 'INV-VOID-CHECK', 15000000);

    $voidCharge = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 10000000,
        'description' => 'Voided EGC Fee',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_VOID,
    ]);

    InvoiceLine::query()->create([
        'invoice_id' => $invoice->id,
        'charge_id' => $voidCharge->id,
        'amount_snapshot' => 10000000,
        'description_snapshot' => 'Voided EGC Fee',
        'status' => 'active',
    ]);

    $result = app(ListSettlementWorklistQuery::class)->handle(Request::create('/finance/operations/settlement', 'GET'));
    $studentRow = collect($result['students']->items())->firstWhere('student_code', 'AUS-VOIDED');

    expect($studentRow)->not->toBeNull()
        ->and($studentRow['active_due'])->toBe(15000000.0)
        ->and($studentRow['invoices'][0]['total_amount'])->toBe(15000000.0);
});

it('lists settlement students in paginated form and applies for selected students only', function () {
    [$studentOne, $semesterOne] = createSettlementStudent('AUS-ONE');
    [$studentTwo, $semesterTwo] = createSettlementStudent('AUS-TWO');

    [$invoiceOne, , $lineOne] = createUnpaidInvoice($studentOne, $semesterOne, 'INV-ONE', 15000000);
    [, , $lineTwo] = createUnpaidInvoice($studentTwo, $semesterTwo, 'INV-TWO', 15000000);

    StudentInvoice::query()->create([
        'invoice_number' => 'INV-ZERO',
        'student_id' => $studentTwo->id,
        'billing_cycle_id' => null,
        'semester_id' => $semesterTwo->id,
        'status' => 'draft',
        'due_date' => now()->addDays(30)->toDateString(),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
    ]);

    Payment::query()->create([
        'student_id' => $studentOne->id,
        'amount' => 10000000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    Payment::query()->create([
        'student_id' => $studentOne->id,
        'amount' => 10000000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now()->addMinute(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    Payment::query()->create([
        'student_id' => $studentTwo->id,
        'amount' => 15000000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $result = app(ListSettlementWorklistQuery::class)->handle(Request::create('/finance/operations/settlement', 'GET', [
        'per_page' => 15,
        'page' => 1,
    ]));

    expect($result['students']->total())->toBe(2)
        ->and($result['students']->items())->toHaveCount(2)
        ->and(collect($result['students']->items())->firstWhere('student_code', 'AUS-ONE')['actionable'])->toBeTrue()
        ->and(collect($result['students']->items())->firstWhere('student_code', 'AUS-ONE')['invoices'][0]['id'])->toBe($invoiceOne->id);

    $stats = app(AutoAllocatePaymentsAction::class)->runForStudents([$studentOne->id], AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER, null);

    expect($stats['students_processed'])->toBe(1)
        ->and($stats['allocations_created'])->toBe(2)
        ->and((float) $invoiceOne->fresh()->paid_amount)->toBe(15000000.0)
        ->and($lineOne->paymentApplications()->count())->toBe(2)
        ->and($lineTwo->paymentApplications()->count())->toBe(0);
});

it('applies payments oldest invoice first then oldest line within invoice', function () {
    [$student, $semester] = createSettlementStudent('AUS-ORDER');

    $oldInvoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-OLD',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(10)->toDateString(),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $newInvoice = StudentInvoice::query()->create([
        'invoice_number' => 'INV-NEW',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(20)->toDateString(),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 0,
        'paid_amount' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $chargeOne = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 10000000,
        'description' => 'Older Line',
        'effective_at' => now()->subDays(2),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $chargeTwo = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_MANUAL_FEE,
        'amount' => 10000000,
        'description' => 'Newer Line Same Invoice',
        'effective_at' => now()->subDays(2),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $chargeThree = FinanceCharge::query()->create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'billing_cycle_id' => null,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 10000000,
        'description' => 'Newest Invoice Line',
        'effective_at' => now()->subDay(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $lineOne = InvoiceLine::query()->create([
        'invoice_id' => $oldInvoice->id,
        'charge_id' => $chargeOne->id,
        'amount_snapshot' => 10000000,
        'description_snapshot' => 'Older Line',
        'status' => 'active',
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $lineTwo = InvoiceLine::query()->create([
        'invoice_id' => $oldInvoice->id,
        'charge_id' => $chargeTwo->id,
        'amount_snapshot' => 10000000,
        'description_snapshot' => 'Newer Line Same Invoice',
        'status' => 'active',
        'created_at' => now()->subDays(2)->addMinute(),
        'updated_at' => now()->subDays(2)->addMinute(),
    ]);

    $lineThree = InvoiceLine::query()->create([
        'invoice_id' => $newInvoice->id,
        'charge_id' => $chargeThree->id,
        'amount_snapshot' => 10000000,
        'description_snapshot' => 'Newest Invoice Line',
        'status' => 'active',
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => 25000000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    $stats = app(AutoAllocatePaymentsAction::class)->runForStudents([$student->id], AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER, null);

    expect($stats['allocations_created'])->toBe(3)
        ->and((float) $lineOne->fresh()->paymentApplications()->sum('amount'))->toBe(10000000.0)
        ->and((float) $lineTwo->fresh()->paymentApplications()->sum('amount'))->toBe(10000000.0)
        ->and((float) $lineThree->fresh()->paymentApplications()->sum('amount'))->toBe(5000000.0)
        ->and($payment->fresh()->unapplied_amount)->toBe(0.0);
});
