<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

require_once __DIR__.'/../Batch/helpers.php';

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->semester = Semester::factory()->create(['is_active' => true]);
    $this->user = User::factory()->create();

    session([
        'current_campus_id' => $this->campus->id,
        'current_semester_id' => $this->semester->id,
    ]);
});

function cpStudent(Campus $campus, Semester $semester, string $code, string $status = 'intake_course'): Student
{
    return Student::factory()->forCampus($campus)->create([
        'student_id' => $code,
        'status' => $status,
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}

/**
 * Bill a student a single tuition line and return [invoice, line].
 *
 * @return array{0: StudentInvoice, 1: InvoiceLine}
 */
function cpBill(Student $student, Semester $semester, float $amount, ?Carbon $dueDate = null): array
{
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-CP-'.$student->id.'-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => $dueDate ?? now()->addDays(30),
    ]);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Tuition',
        'status' => 'active',
    ]);

    app(SettlementService::class)->recalculateInvoiceSnapshot($invoice);

    return [$invoice->fresh(), $line];
}

function cpPay(Student $student, InvoiceLine $line, float $paymentAmount, ?float $applyAmount = null): Payment
{
    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => $paymentAmount,
        'method' => Payment::METHOD_CASH,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
    ]);

    $apply = $applyAmount ?? $paymentAmount;
    if ($apply > 0) {
        app(SettlementService::class)->createPaymentApplication($payment, $line, $apply, 'application');
    }

    return $payment;
}

function cpRows(TestResponse $response): Collection
{
    return collect($response->original->getData()['page']['props']['collection_progress']['rows']['data']);
}

it('renders collection progress props with summary, breakdowns, filters, and campus scope', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = cpStudent($this->campus, $this->semester, 'CP-001');
    [, $line] = cpBill($student, $this->semester, 10_000_000);
    cpPay($student, $line, 4_000_000);

    $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'collection-progress']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Finance/Reporting/Index', false)
            ->where('active_view', 'collection-progress')
            ->where('views.1.key', 'collection-progress')
            ->where('views.1.status', 'implemented')
            ->has('collection_progress.rows')
            ->has('collection_progress.summary')
            ->has('collection_progress.breakdowns.by_fee_type')
            ->has('collection_progress.breakdowns.by_aging_bucket')
            ->has('collection_progress.filters')
            ->has('collection_progress.filter_options.balance_states')
            ->has('collection_progress.filter_options.aging_buckets')
            ->where('collection_progress.meta.semester_id', $this->semester->id)
            ->has('collection_progress.computed_at')
        );
});

it('derives billed, paid, and outstanding from canonical settlement truth', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = cpStudent($this->campus, $this->semester, 'CP-PARTIAL');
    [, $line] = cpBill($student, $this->semester, 10_000_000);
    cpPay($student, $line, 4_000_000);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'collection-progress', 'search' => 'CP-PARTIAL']))
        ->assertOk();

    $row = cpRows($response)->firstWhere('student.student_code', 'CP-PARTIAL');

    expect($row)->not->toBeNull()
        ->and($row['billed'])->toEqual(10_000_000.0)
        ->and($row['paid'])->toEqual(4_000_000.0)
        ->and($row['outstanding'])->toEqual(6_000_000.0)
        ->and($row['balance_state'])->toBe('partially_paid')
        ->and($row['drilldowns']['lookup_invoice_id'])->not->toBeNull()
        ->and($row['drilldowns']['student_360_focus'])->toContain('invoice:');
});

it('flags overdue balances and aging buckets from invoice due dates', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = cpStudent($this->campus, $this->semester, 'CP-OVERDUE');
    cpBill($student, $this->semester, 8_000_000, now()->subDays(10));

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'collection-progress', 'search' => 'CP-OVERDUE']))
        ->assertOk();

    $row = cpRows($response)->firstWhere('student.student_code', 'CP-OVERDUE');

    expect($row['overdue'])->toEqual(8_000_000.0)
        ->and($row['balance_state'])->toBe('overdue')
        ->and($row['aging_bucket'])->toBe('d_1_30');
});

it('reports unapplied cash and supports the unapplied balance-state filter', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $student = cpStudent($this->campus, $this->semester, 'CP-UNAPPLIED');
    [, $line] = cpBill($student, $this->semester, 10_000_000);
    // Pay 10M but only apply 4M → 6M sits as unapplied cash on the payment.
    cpPay($student, $line, 10_000_000, 4_000_000);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', [
            'view' => 'collection-progress',
            'balance_state' => 'unapplied',
        ]))
        ->assertOk();

    $rows = cpRows($response);
    $row = $rows->firstWhere('student.student_code', 'CP-UNAPPLIED');

    expect($row)->not->toBeNull()
        ->and($row['unapplied'])->toEqual(6_000_000.0)
        ->and($row['has_unapplied'])->toBeTrue()
        ->and($rows->every(fn (array $r) => $r['unapplied'] > 0))->toBeTrue();
});

it('filters fully paid students by the paid balance state', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $paidStudent = cpStudent($this->campus, $this->semester, 'CP-PAID');
    [, $paidLine] = cpBill($paidStudent, $this->semester, 5_000_000);
    cpPay($paidStudent, $paidLine, 5_000_000);

    $unpaidStudent = cpStudent($this->campus, $this->semester, 'CP-UNPAID');
    cpBill($unpaidStudent, $this->semester, 5_000_000);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', [
            'view' => 'collection-progress',
            'balance_state' => 'paid',
        ]))
        ->assertOk();

    $codes = cpRows($response)->pluck('student.student_code');

    expect($codes)->toContain('CP-PAID')
        ->not->toContain('CP-UNPAID');
});

it('scopes rows to the current campus and selected semester', function () {
    grantFinance($this->user, ['view_finance_reporting'], $this->campus);

    $inScope = cpStudent($this->campus, $this->semester, 'CP-IN');
    cpBill($inScope, $this->semester, 3_000_000);

    $otherCampus = Campus::factory()->create();
    $otherStudent = cpStudent($otherCampus, $this->semester, 'CP-OTHER-CAMPUS');
    cpBill($otherStudent, $this->semester, 3_000_000);

    $otherSemester = Semester::factory()->create();
    $otherSemesterStudent = cpStudent($this->campus, $otherSemester, 'CP-OTHER-SEM');
    cpBill($otherSemesterStudent, $otherSemester, 3_000_000);

    $response = $this->actingAs($this->user)
        ->get(route('finance.reporting.index', ['view' => 'collection-progress']))
        ->assertOk();

    $codes = cpRows($response)->pluck('student.student_code');

    expect($codes)->toContain('CP-IN')
        ->not->toContain('CP-OTHER-CAMPUS')
        ->not->toContain('CP-OTHER-SEM');
});
