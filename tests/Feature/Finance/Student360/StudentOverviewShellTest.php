<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\FinanceCharge;
use App\Models\FinanceChargeInstallment;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Services\SettlementService;
use App\Services\PermissionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

if (! function_exists('grantFinanceOverview')) {
    function grantFinanceOverview(array $codes): User
    {
        $user = User::factory()->create();
        $mock = Mockery::mock(PermissionService::class);
        $mock->shouldReceive('getUserPermissions')->andReturn($codes);
        app()->singleton(PermissionService::class, fn () => $mock);

        return $user;
    }
}

beforeEach(function () {
    $this->campus = Campus::factory()->create();
    $this->otherCampus = Campus::factory()->create();
    $this->program = Program::factory()->create();
    $this->semester = Semester::factory()->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);
});

function makeOverviewStudent(Campus $campus, Program $program, Semester $semester): Student
{
    return Student::factory()->forCampus($campus)->forProgram($program)
        ->state(['intake' => 1, 'intake_semester_id' => $semester->id])
        ->create();
}

/**
 * @return array{charge: FinanceCharge, invoice: StudentInvoice, line: InvoiceLine}
 */
function makeOverviewCharge(
    Student $student,
    Semester $semester,
    float $amount = 10_000_000,
    string $chargeStatus = FinanceCharge::STATUS_ACTIVE,
    string $lineStatus = 'active',
): array {
    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => $amount,
        'description' => 'Overview tuition',
        'effective_at' => now(),
        'status' => $chargeStatus,
        'voided_at' => $chargeStatus === FinanceCharge::STATUS_VOID ? now() : null,
        'void_reason' => $chargeStatus === FinanceCharge::STATUS_VOID ? 'Voided test charge' : null,
    ]);
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-OVERVIEW-'.$student->id.'-'.$charge->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => $lineStatus === 'void' ? 'cancelled' : 'pending',
        'due_date' => now()->addDays(7),
    ]);
    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => $amount,
        'description_snapshot' => 'Overview tuition',
        'status' => $lineStatus,
        'voided_at' => $lineStatus === 'void' ? now() : null,
        'void_reason' => $lineStatus === 'void' ? 'Voided test line' : null,
    ]);

    return ['charge' => $charge, 'invoice' => $invoice, 'line' => $line];
}

it('renders the 360 shell with identity and four balances for a visible student', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Student360/Show')
            ->where('student.id', $student->id)
            ->where('student.student_code', $student->student_id)
            ->has('student.lifecycle_reason')
            ->has('balances.net_charges')
            ->has('balances.total_paid')
            ->has('balances.balance')
            ->has('balances.unapplied_credit')
            ->where('focus', null));
});

it('shows staff-facing tuition KPIs and surplus source for a paid DNG released from voided fees', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-10 09:00:00'));

    try {
        $user = grantFinanceOverview(['view_finance_student_overview']);
        $student = makeOverviewStudent($this->campus, $this->program, $this->semester);
        $student->forceFill(['student_id' => 'AUS121787'])->save();
        $summer = Semester::factory()->create(['code' => 'SUMMER2026', 'name' => 'Summer 2026']);

        $springCharge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => 15_000_000,
            'description' => 'SPRING tuition',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
        $springInvoice = StudentInvoice::create([
            'invoice_number' => 'INV-SPRING-AUS121787',
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);
        $springLine = InvoiceLine::create([
            'invoice_id' => $springInvoice->id,
            'charge_id' => $springCharge->id,
            'amount_snapshot' => 15_000_000,
            'description_snapshot' => 'SPRING tuition',
            'status' => 'active',
        ]);

        $springPayment = Payment::create([
            'student_id' => $student->id,
            'amount' => 15_000_000,
            'method' => Payment::METHOD_BANK_TRANSFER,
            'source' => 'manual',
            'external_ref' => 'SPRING-PAID',
            'paid_at' => CarbonImmutable::parse('2026-04-20 10:30:00'),
            'status' => Payment::STATUS_COMPLETED,
            'received_by_user_id' => $user->id,
        ]);
        app(SettlementService::class)->createPaymentApplication($springPayment, $springLine, 15_000_000, 'application', $user->id);

        $summerCharge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $summer->id,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => 30_000_000,
            'description' => 'SUMMER EGC fees',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_VOID,
            'voided_at' => now(),
            'voided_by_user_id' => $user->id,
            'void_reason' => 'SUMMER2026 fees voided after DNG payment',
        ]);
        $summerInvoice = StudentInvoice::create([
            'invoice_number' => 'INV-SUMMER-AUS121787',
            'student_id' => $student->id,
            'semester_id' => $summer->id,
            'status' => 'paid',
            'due_date' => now()->addDays(14),
        ]);
        $summerLine = InvoiceLine::create([
            'invoice_id' => $summerInvoice->id,
            'charge_id' => $summerCharge->id,
            'amount_snapshot' => 30_000_000,
            'description_snapshot' => 'SUMMER EGC fees',
            'status' => 'void',
            'voided_at' => now(),
            'void_reason' => 'SUMMER2026 fees voided after DNG payment',
        ]);

        $dngPayment = Payment::create([
            'student_id' => $student->id,
            'amount' => 30_000_000,
            'method' => Payment::METHOD_GATEWAY,
            'source' => 'dng',
            'external_ref' => 'DNGPAY-230',
            'paid_at' => CarbonImmutable::parse('2026-05-02 08:00:00'),
            'status' => Payment::STATUS_COMPLETED,
            'received_by_user_id' => $user->id,
        ]);
        PaymentApplication::create([
            'payment_id' => $dngPayment->id,
            'invoice_line_id' => $summerLine->id,
            'amount' => 30_000_000,
            'entry_type' => 'application',
            'applied_at' => CarbonImmutable::parse('2026-05-02 08:05:00'),
            'created_by' => $user->id,
        ]);
        PaymentApplication::create([
            'payment_id' => $dngPayment->id,
            'invoice_line_id' => $summerLine->id,
            'amount' => -30_000_000,
            'entry_type' => 'reversal',
            'applied_at' => CarbonImmutable::parse('2026-05-03 09:00:00'),
            'created_by' => $user->id,
            'source_ref_type' => 'finance_charge',
            'source_ref_id' => $summerCharge->id,
        ]);
        DngPaymentRequest::create([
            'student_id' => $student->id,
            'campus_code' => 'CAMPUS001',
            'student_code' => 'AUS121787',
            'fee_type' => 'HP',
            'description' => 'SUMMER2026 EGC',
            'semester_id' => $summer->id,
            'due_date' => CarbonImmutable::parse('2026-05-15'),
            'item_id' => '230',
            'amount' => 30_000_000,
            'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
            'dng_payment_id' => 'DNGPAY-230',
            'payment_id' => $dngPayment->id,
            'paid_at' => CarbonImmutable::parse('2026-05-02 08:00:00'),
        ]);

        actingAs($user)->get("/finance/students/{$student->id}")
            ->assertInertia(fn ($page) => $page
                ->component('Finance/Student360/Show')
                ->where('tuition_overview.title', 'Học phí sinh viên')
                ->where('tuition_overview.kpis.collectible_due.label', 'Còn phải thu')
                ->where('tuition_overview.kpis.collectible_due.amount', 0)
                ->where('tuition_overview.kpis.collectible_due.primary', true)
                ->where('tuition_overview.kpis.total_paid.label', 'Tổng tiền đã nộp')
                ->where('tuition_overview.kpis.total_paid.amount', 45_000_000)
                ->where('tuition_overview.kpis.collected.label', 'Đã thu')
                ->where('tuition_overview.kpis.collected.amount', 15_000_000)
                ->where('tuition_overview.kpis.surplus.label', 'Còn dư')
                ->where('tuition_overview.kpis.surplus.amount', 30_000_000)
                ->where('tuition_overview.surplus_message', 'Còn dư 30.000.000 từ DNG #230, đã nộp ngày 02/05/2026')
                ->has('payment_history', 2)
                ->where('payment_history.0.id', $dngPayment->id)
                ->where('payment_history.0.source_label', 'DNG')
                ->where('payment_history.0.reference', 'DNG #230')
                ->where('payment_history.0.amount_paid', 30_000_000)
                ->where('payment_history.0.collected_amount', 0)
                ->where('payment_history.0.surplus_amount', 30_000_000)
                ->where('payment_history.0.status_label', 'Còn dư')
                ->where('payment_history.0.action.can_allocate', false)
                ->where('payment_history.0.action.message', 'Chưa có học phí/khoản phí hiện tại để phân bổ.')
                ->where('payment_history.1.id', $springPayment->id)
                ->where('payment_history.1.source_label', 'Thủ công')
                ->where('payment_history.1.reference', 'SPRING-PAID')
                ->where('payment_history.1.amount_paid', 15_000_000)
                ->where('payment_history.1.collected_amount', 15_000_000)
                ->where('payment_history.1.surplus_amount', 0)
                ->where('payment_history.1.status_label', 'Đã thu hết')
                ->where('payment_history.1.action.can_allocate', false));
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('surfaces in-page review signals for surplus, voided DNG fees, stale installments, and cache drift', function () {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-10 09:00:00'));

    try {
        $user = grantFinanceOverview(['view_finance_student_overview']);
        $student = makeOverviewStudent($this->campus, $this->program, $this->semester);
        $summer = Semester::factory()->create(['code' => 'SUMMER2026', 'name' => 'Summer 2026']);

        $paidCharge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => 15_000_000,
            'description' => 'Paid tuition with stale installment',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
        $paidInvoice = StudentInvoice::create([
            'invoice_number' => 'INV-PAID-STILL-CACHED-OLD',
            'student_id' => $student->id,
            'semester_id' => $this->semester->id,
            'status' => 'pending',
            'due_date' => now()->addDays(7),
        ]);
        $paidLine = InvoiceLine::create([
            'invoice_id' => $paidInvoice->id,
            'charge_id' => $paidCharge->id,
            'amount_snapshot' => 15_000_000,
            'description_snapshot' => 'Paid tuition with stale installment',
            'status' => 'active',
        ]);
        FinanceChargeInstallment::create([
            'finance_charge_id' => $paidCharge->id,
            'installment_no' => 1,
            'amount' => 15_000_000,
            'due_date' => now()->addDays(7),
            'status' => FinanceChargeInstallment::STATUS_PENDING,
        ]);

        $paidInFull = Payment::create([
            'student_id' => $student->id,
            'amount' => 15_000_000,
            'method' => Payment::METHOD_BANK_TRANSFER,
            'source' => 'manual',
            'external_ref' => 'PAID-IN-FULL',
            'paid_at' => CarbonImmutable::parse('2026-04-20 10:30:00'),
            'status' => Payment::STATUS_COMPLETED,
            'received_by_user_id' => $user->id,
        ]);
        app(SettlementService::class)->createPaymentApplication($paidInFull, $paidLine, 15_000_000, 'application', $user->id);
        $paidInvoice->forceFill([
            'cached_total_amount' => 1_000,
            'cached_paid_amount' => 0,
        ])->save();

        $voidedCharge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $summer->id,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => 30_000_000,
            'description' => 'Voided EGC fee',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_VOID,
            'voided_at' => now(),
            'voided_by_user_id' => $user->id,
            'void_reason' => 'Voided after DNG payment',
        ]);
        $voidedInvoice = StudentInvoice::create([
            'invoice_number' => 'INV-VOIDED-DNG',
            'student_id' => $student->id,
            'semester_id' => $summer->id,
            'status' => 'cancelled',
            'due_date' => now()->addDays(14),
        ]);
        $voidedLine = InvoiceLine::create([
            'invoice_id' => $voidedInvoice->id,
            'charge_id' => $voidedCharge->id,
            'amount_snapshot' => 30_000_000,
            'description_snapshot' => 'Voided EGC fee',
            'status' => 'void',
            'voided_at' => now(),
            'void_reason' => 'Voided after DNG payment',
        ]);

        $dngPayment = Payment::create([
            'student_id' => $student->id,
            'amount' => 30_000_000,
            'method' => Payment::METHOD_GATEWAY,
            'source' => 'dng',
            'external_ref' => 'DNGPAY-230',
            'paid_at' => CarbonImmutable::parse('2026-05-02 08:00:00'),
            'status' => Payment::STATUS_COMPLETED,
            'received_by_user_id' => $user->id,
        ]);
        PaymentApplication::create([
            'payment_id' => $dngPayment->id,
            'invoice_line_id' => $voidedLine->id,
            'amount' => 30_000_000,
            'entry_type' => 'application',
            'applied_at' => CarbonImmutable::parse('2026-05-02 08:05:00'),
            'created_by' => $user->id,
        ]);
        PaymentApplication::create([
            'payment_id' => $dngPayment->id,
            'invoice_line_id' => $voidedLine->id,
            'amount' => -30_000_000,
            'entry_type' => 'reversal',
            'applied_at' => CarbonImmutable::parse('2026-05-03 09:00:00'),
            'created_by' => $user->id,
            'source_ref_type' => 'finance_charge',
            'source_ref_id' => $voidedCharge->id,
        ]);
        DngPaymentRequest::create([
            'student_id' => $student->id,
            'campus_code' => 'CAMPUS001',
            'student_code' => $student->student_id,
            'fee_type' => 'HP',
            'description' => 'SUMMER2026 EGC',
            'semester_id' => $summer->id,
            'due_date' => CarbonImmutable::parse('2026-05-15'),
            'item_id' => '230',
            'amount' => 30_000_000,
            'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
            'payment_id' => $dngPayment->id,
            'paid_at' => CarbonImmutable::parse('2026-05-02 08:00:00'),
        ]);

        actingAs($user)->get("/finance/students/{$student->id}")
            ->assertInertia(fn ($page) => $page
                ->has('review_signals', 4)
                ->where('review_signals.0.type', 'surplus')
                ->where('review_signals.0.target', 'payment-history')
                ->where('review_signals.0.target_label', 'Xem khoản đã nộp')
                ->where('review_signals.1.type', 'dng_paid_voided_fee')
                ->where('review_signals.1.target', 'ledger')
                ->where('review_signals.1.target_label', 'Xem sổ cái')
                ->where('review_signals.2.type', 'installment_mismatch')
                ->where('review_signals.2.target', 'installments')
                ->where('review_signals.2.target_label', 'Xem thẻ trả góp')
                ->where('review_signals.3.type', 'invoice_cache_drift')
                ->where('review_signals.3.target', 'ledger')
                ->where('review_signals.3.target_label', 'Xem sổ cái'));
    } finally {
        CarbonImmutable::setTestNow();
    }
});

it('does not show review signals when payment, installment, and invoice cache data is consistent', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);
    $fixture = makeOverviewCharge($student, $this->semester);

    FinanceChargeInstallment::create([
        'finance_charge_id' => $fixture['charge']->id,
        'installment_no' => 1,
        'amount' => 10_000_000,
        'due_date' => now()->subDay(),
        'status' => FinanceChargeInstallment::STATUS_PAID,
        'paid_at' => now()->subDay(),
    ]);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 10_000_000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'manual',
        'external_ref' => 'CONSISTENT-PAID',
        'paid_at' => now()->subDay(),
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $user->id,
    ]);
    app(SettlementService::class)->createPaymentApplication($payment, $fixture['line'], 10_000_000, 'application', $user->id);
    app(SettlementService::class)->recalculateInvoiceSnapshot($fixture['invoice']);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page->where('review_signals', []));
});

it('offers allocation from a surplus payment only when current fee obligations exist', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5_000_000,
        'description' => 'Current tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-CURRENT-'.$student->id,
        'student_id' => $student->id,
        'semester_id' => $this->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
    ]);
    InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 5_000_000,
        'description_snapshot' => 'Current tuition',
        'status' => 'active',
    ]);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 2_000_000,
        'method' => Payment::METHOD_IMPORT,
        'source' => 'import',
        'external_ref' => 'IMPORT-2026-001',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $user->id,
    ]);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->has('payment_history', 1)
            ->where('payment_history.0.id', $payment->id)
            ->where('payment_history.0.source_label', 'Import')
            ->where('payment_history.0.status_label', 'Còn dư')
            ->where('payment_history.0.action.can_allocate', true)
            ->where('payment_history.0.action.message', null));
});

it('does not make a paid DNG request actionable on the DNG card', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 3_000_000,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'external_ref' => 'DNGPAY-PAID-001',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $user->id,
    ]);
    DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'description' => 'Paid DNG',
        'semester_id' => $this->semester->id,
        'due_date' => now()->addDays(7),
        'item_id' => 'PAID-001',
        'amount' => 3_000_000,
        'status' => DngPaymentRequest::STATUS_PAID_INVOICED,
        'payment_id' => $payment->id,
        'paid_at' => now(),
    ]);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('status_cards.dng.has_active', false)
            ->where('status_cards.dng.request', null)
            ->has('payment_history', 1)
            ->where('payment_history.0.reference', 'DNG #PAID-001')
            ->where('payment_history.0.status_label', 'Còn dư'));
});

it('surfaces live and failed DNG requests as actionable DNG work', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    $dng = DngPaymentRequest::create([
        'student_id' => $student->id,
        'campus_code' => 'CAMPUS001',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'description' => 'Live DNG',
        'semester_id' => $this->semester->id,
        'due_date' => now()->addDays(7),
        'item_id' => 'LIVE-001',
        'amount' => 4_000_000,
        'status' => DngPaymentRequest::STATUS_PENDING,
    ]);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('status_cards.dng.has_active', true)
            ->where('status_cards.dng.request.id', $dng->id)
            ->where('status_cards.dng.request.status', DngPaymentRequest::STATUS_PENDING));

    $dng->update(['status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG]);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('status_cards.dng.has_active', true)
            ->where('status_cards.dng.request.id', $dng->id)
            ->where('status_cards.dng.request.status', DngPaymentRequest::STATUS_PUSHED_TO_DNG));

    $dng->update([
        'status' => DngPaymentRequest::STATUS_FAILED,
        'error_message' => 'DNG push failed',
    ]);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('status_cards.dng.has_active', true)
            ->where('status_cards.dng.request.id', $dng->id)
            ->where('status_cards.dng.request.status', DngPaymentRequest::STATUS_FAILED)
            ->where('status_cards.dng.request.error_message', 'DNG push failed'));
});

it('hides pending installments once the invoice is fully paid', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);
    $fixture = makeOverviewCharge($student, $this->semester);

    FinanceChargeInstallment::create([
        'finance_charge_id' => $fixture['charge']->id,
        'installment_no' => 1,
        'amount' => 5_000_000,
        'due_date' => now()->addDays(7),
        'status' => FinanceChargeInstallment::STATUS_PENDING,
    ]);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 10_000_000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'manual',
        'external_ref' => 'FULL-PAID',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $user->id,
    ]);
    app(SettlementService::class)->createPaymentApplication($payment, $fixture['line'], 10_000_000, 'application', $user->id);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('status_cards.installments.total', 0)
            ->where('status_cards.installments.paid', 0)
            ->where('status_cards.installments.next', null));
});

it('hides pending and cancelled installments on voided charges', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);
    $fixture = makeOverviewCharge(
        student: $student,
        semester: $this->semester,
        chargeStatus: FinanceCharge::STATUS_VOID,
        lineStatus: 'void',
    );

    FinanceChargeInstallment::create([
        'finance_charge_id' => $fixture['charge']->id,
        'installment_no' => 1,
        'amount' => 5_000_000,
        'due_date' => now()->addDays(7),
        'status' => FinanceChargeInstallment::STATUS_PENDING,
    ]);
    FinanceChargeInstallment::create([
        'finance_charge_id' => $fixture['charge']->id,
        'installment_no' => 2,
        'amount' => 5_000_000,
        'due_date' => now()->addDays(14),
        'status' => FinanceChargeInstallment::STATUS_CANCELLED,
    ]);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->where('status_cards.installments.total', 0)
            ->where('status_cards.installments.paid', 0)
            ->where('status_cards.installments.next', null));
});

it('keeps voided invoice lines in grouped ledger without counting them as collectible', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);
    $student->forceFill(['student_id' => 'AUS121787'])->save();

    $spring = Semester::factory()->create(['code' => 'SPRING2026', 'name' => 'Spring 2026']);
    $summer = Semester::factory()->create(['code' => 'SUMMER2026', 'name' => 'Summer 2026']);

    $springActiveCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $spring->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 5_000_000,
        'description' => 'SPRING active tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);
    $springVoidedCharge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $spring->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 10_000_000,
        'description' => 'SPRING voided EGC add-on',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_VOID,
        'voided_at' => now(),
        'voided_by_user_id' => $user->id,
        'void_reason' => 'Wrong EGC level',
    ]);
    $springInvoice = StudentInvoice::create([
        'invoice_number' => 'INV-SPRING-LEDGER',
        'student_id' => $student->id,
        'semester_id' => $spring->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
    ]);
    InvoiceLine::create([
        'invoice_id' => $springInvoice->id,
        'charge_id' => $springActiveCharge->id,
        'amount_snapshot' => 5_000_000,
        'description_snapshot' => 'SPRING active tuition',
        'status' => 'active',
    ]);
    InvoiceLine::create([
        'invoice_id' => $springInvoice->id,
        'charge_id' => $springVoidedCharge->id,
        'amount_snapshot' => 10_000_000,
        'description_snapshot' => 'SPRING voided EGC add-on',
        'status' => 'void',
        'voided_at' => now(),
        'void_reason' => 'Wrong EGC level',
    ]);

    $summerInvoice = StudentInvoice::create([
        'invoice_number' => 'INV-SUMMER-AUS121787',
        'student_id' => $student->id,
        'semester_id' => $summer->id,
        'status' => 'cancelled',
        'due_date' => now()->addDays(14),
    ]);
    $dngPayment = Payment::create([
        'student_id' => $student->id,
        'amount' => 30_000_000,
        'method' => Payment::METHOD_GATEWAY,
        'source' => 'dng',
        'external_ref' => 'DNGPAY-230',
        'paid_at' => now()->subDay(),
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $user->id,
    ]);

    foreach ([1, 2] as $block) {
        $charge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $summer->id,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => 15_000_000,
            'description' => "SUMMER2026 EGC Block {$block}",
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_VOID,
            'voided_at' => now(),
            'voided_by_user_id' => $user->id,
            'void_reason' => 'SUMMER2026 fees voided after DNG payment',
        ]);
        $line = InvoiceLine::create([
            'invoice_id' => $summerInvoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => 15_000_000,
            'description_snapshot' => "SUMMER2026 EGC Block {$block}",
            'status' => 'void',
            'voided_at' => now(),
            'void_reason' => 'SUMMER2026 fees voided after DNG payment',
        ]);

        PaymentApplication::create([
            'payment_id' => $dngPayment->id,
            'invoice_line_id' => $line->id,
            'amount' => 15_000_000,
            'entry_type' => 'application',
            'applied_at' => now()->subDay(),
            'created_by' => $user->id,
        ]);
        PaymentApplication::create([
            'payment_id' => $dngPayment->id,
            'invoice_line_id' => $line->id,
            'amount' => -15_000_000,
            'entry_type' => 'reversal',
            'applied_at' => now(),
            'created_by' => $user->id,
            'source_ref_type' => 'finance_charge',
            'source_ref_id' => $charge->id,
        ]);
    }

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('default', fn ($reload) => $reload
                ->where('ledger_groups.0.semester.code', 'SUMMER2026')
                ->where('ledger_groups.0.collectible_remaining', 0)
                ->where('ledger_groups.0.state_label', 'Không còn phải thu')
                ->has('ledger_groups.0.invoices.0.lines', 2)
                ->where('ledger_groups.0.invoices.0.lines.0.status', 'void')
                ->where('ledger_groups.0.invoices.0.lines.0.status_label', 'Đã hủy')
                ->where('ledger_groups.0.invoices.0.lines.0.outstanding', 0)
                ->where('ledger_groups.0.invoices.0.lines.0.payment_applied', 15_000_000)
                ->where('ledger_groups.0.invoices.0.lines.0.payment_reversed', 15_000_000)
                ->where('ledger_groups.1.semester.code', 'SPRING2026')
                ->where('ledger_groups.1.collectible_remaining', 5_000_000)
                ->has('ledger_groups.1.invoices.0.lines', 2)
                ->where('ledger_groups.1.invoices.0.lines.0.status', 'active')
                ->where('ledger_groups.1.invoices.0.lines.0.outstanding', 5_000_000)
                ->where('ledger_groups.1.invoices.0.lines.1.status', 'void')
                ->where('ledger_groups.1.invoices.0.lines.1.status_label', 'Đã hủy')
                ->where('ledger_groups.1.invoices.0.lines.1.outstanding', 0)
                ->where('ledger_groups.1.invoices.0.remaining', 5_000_000)));
});

it('echoes a valid focus target', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}?focus=dng:7781")
        ->assertInertia(fn ($page) => $page
            ->where('focus.type', 'dng')
            ->where('focus.id', 7781));
});

it('denies access without the permission', function () {
    $user = grantFinanceOverview([]);
    $student = makeOverviewStudent($this->campus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")->assertForbidden();
});

it('hides a cross-campus student as not found', function () {
    $user = grantFinanceOverview(['view_finance_student_overview']);
    $student = makeOverviewStudent($this->otherCampus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")->assertNotFound();
});

it('allows cross-campus view with the all-campus permission', function () {
    $user = grantFinanceOverview(['view_finance_student_overview', 'view_finance_all_campus']);
    $student = makeOverviewStudent($this->otherCampus, $this->program, $this->semester);

    actingAs($user)->get("/finance/students/{$student->id}")
        ->assertInertia(fn ($page) => $page->component('Finance/Student360/Show'));
});
