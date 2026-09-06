<?php

declare(strict_types=1);

use App\Http\Middleware\VerifyCsrfToken;
use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\AutoAllocatePaymentsAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\Student360\GetStudent360StatusCardsQuery;
use App\Modules\Finance\Queries\Student360\GetStudentFinanceReviewSignalsQuery;
use App\Modules\Finance\Queries\Student360\PreviewManualAllocationQuery;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Shared\Contracts\Identity\CampusPermissionReader;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
        ->andReturn(['allocate_finance_payment', 'view_finance_payments']);

    app()->singleton(CampusPermissionReader::class, fn () => $permissionService);
});

it('redirects the auto-allocate page to settlement', function () {
    $response = $this->actingAs($this->user)
        ->get(route('finance.payments.auto-allocate.show'));

    $response->assertRedirect(route('finance.operations.settlement.index'));
});

it('returns preview data for auto-allocation', function () {
    $student = autoAllocateStudent($this);
    autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_TUITION_TERM, 10_000_000, 'TEST001');
    autoAllocatePayment($student, 5_000_000);

    $response = $this->actingAs($this->user)
        ->postJson(route('finance.payments.auto-allocate.preview'), [
            'priority_order' => ObligationTypeRegistry::allocationPriorityOrder(),
        ]);
    $response->assertOk();
    $response->assertJsonStructure([
        'students' => [
            '*' => [
                'student_id',
                'student_code',
                'student_name',
                'payments',
                'allocations',
                'total_to_allocate',
            ],
        ],
        'summary' => [
            'total_students',
            'total_payments_affected',
            'total_allocations',
            'total_amount',
            'invoices_to_update',
        ],
    ]);

    $response->assertJsonPath('summary.total_students', 1);
    $response->assertJsonPath('summary.total_amount', 5000000);
});

it('returns empty preview when no unapplied payments exist', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('finance.payments.auto-allocate.preview'), [
            'priority_order' => ObligationTypeRegistry::allocationPriorityOrder(),
        ]);
    $response->assertOk();
    $response->assertJsonPath('summary.total_students', 0);
    $response->assertJsonPath('summary.total_amount', 0);
});

it('validates priority_order is required for preview', function () {
    $response = $this->actingAs($this->user)
        ->postJson(route('finance.payments.auto-allocate.preview'), []);

    $response->assertStatus(422);
    $response->assertJsonPath('errors.0.field', 'priority_order');
});

it('executes auto-allocation and redirects with success', function () {
    $student = autoAllocateStudent($this);
    autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_TUITION_TERM, 10_000_000, 'TEST002');
    autoAllocatePayment($student, 5_000_000);

    $response = $this->actingAs($this->user)
        ->post(route('finance.payments.auto-allocate'), [
            'priority_order' => ObligationTypeRegistry::allocationPriorityOrder(),
        ]);
    $response->assertRedirect(route('finance.payments.index'));
    $response->assertSessionHas('success');
});

it('keeps the action default order identical to the registry policy', function () {
    expect(AutoAllocatePaymentsAction::DEFAULT_PRIORITY_ORDER)
        ->toBe(ObligationTypeRegistry::allocationPriorityOrder())
        ->toHaveCount(8);
});

it('rejects a typo in priority_order without allocating', function () {
    $student = autoAllocateStudent($this);
    autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_TUITION_TERM, 1_000_000, 'TYPO');
    $payment = autoAllocatePayment($student, 1_000_000);

    $order = ObligationTypeRegistry::allocationPriorityOrder();
    $order[0] = 'tuition_termm';

    $this->actingAs($this->user)
        ->postJson(route('finance.payments.auto-allocate'), [
            'priority_order' => $order,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.0.field', 'priority_order');

    expect(PaymentApplication::query()->where('payment_id', $payment->id)->count())->toBe(0);
});

it('rejects a priority_order that omits a debit type', function () {
    $order = ObligationTypeRegistry::allocationPriorityOrder();
    array_pop($order);

    $this->actingAs($this->user)
        ->postJson(route('finance.payments.auto-allocate.preview'), [
            'priority_order' => $order,
        ])
        ->assertStatus(422)
        ->assertJsonPath('errors.0.field', 'priority_order');
});

it('allocates exam_resit_fee before manual_fee', function () {
    $student = autoAllocateStudent($this);
    $resit = autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_EXAM_RESIT_FEE, 1_000_000, 'RESIT');
    $manual = autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_MANUAL_FEE, 1_000_000, 'MANUAL');
    $payment = autoAllocatePayment($student, 1_000_000);

    $this->actingAs($this->user)
        ->post(route('finance.payments.auto-allocate'), [
            'priority_order' => ObligationTypeRegistry::allocationPriorityOrder(),
        ])
        ->assertRedirect(route('finance.payments.index'));

    expect(PaymentApplication::query()->where('invoice_line_id', $resit->id)->sum('amount'))->toEqual(1_000_000)
        ->and(PaymentApplication::query()->where('invoice_line_id', $manual->id)->count())->toBe(0)
        ->and(PaymentApplication::query()->where('payment_id', $payment->id)->count())->toBe(1);
});

it('allocates the eight debit types in policy order', function () {
    $student = autoAllocateStudent($this);
    $lines = [];
    foreach (array_reverse(ObligationTypeRegistry::allocationPriorityOrder()) as $index => $type) {
        $lines[$type] = autoAllocateChargeLine($this, $student, $type, 100_000, 'P'.$index);
    }
    autoAllocatePayment($student, 100_000);

    $this->actingAs($this->user)
        ->post(route('finance.payments.auto-allocate'), [
            'priority_order' => ObligationTypeRegistry::allocationPriorityOrder(),
        ])
        ->assertRedirect(route('finance.payments.index'));

    expect(PaymentApplication::query()->where('invoice_line_id', $lines[FinanceCharge::TYPE_TUITION_TERM]->id)->sum('amount'))->toEqual(100_000);

    foreach (ObligationTypeRegistry::allocationPriorityOrder() as $type) {
        if ($type === FinanceCharge::TYPE_TUITION_TERM) {
            continue;
        }
        expect(PaymentApplication::query()->where('invoice_line_id', $lines[$type]->id)->count())->toBe(0);
    }
});

it('rejects cross-campus student_ids without allocating', function () {
    $foreignCampus = Campus::factory()->create();
    $foreign = Student::factory()
        ->forCampus($foreignCampus)
        ->forProgram($this->program)
        ->state([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();
    $line = autoAllocateChargeLine($this, $foreign, FinanceCharge::TYPE_TUITION_TERM, 1_000_000, 'XCAM');
    $payment = autoAllocatePayment($foreign, 1_000_000);

    $this->actingAs($this->user)
        ->post(route('finance.operations.settlement.apply'), [
            'priority_order' => ObligationTypeRegistry::allocationPriorityOrder(),
            'student_ids' => [$foreign->id],
        ])
        ->assertForbidden();

    expect(PaymentApplication::query()->where('payment_id', $payment->id)->count())->toBe(0)
        ->and(PaymentApplication::query()->where('invoice_line_id', $line->id)->count())->toBe(0);
});

it('loads Student 360 reads for a student with bhyt without error', function () {
    $student = autoAllocateStudent($this);
    autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_BHYT, 500_000, 'BHYT');
    autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_ADJUSTMENT, 500_000, 'ADJ');

    expect(app(GetStudent360StatusCardsQuery::class)->handle($student->id))->toBeArray()
        ->and(app(GetStudentFinanceReviewSignalsQuery::class)->handle($student->id))->toBeArray();
});

it('matches preview candidate order with settlement outstanding order', function () {
    $student = autoAllocateStudent($this);
    autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_MANUAL_FEE, 200_000, 'PAR-M');
    autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_EXAM_RESIT_FEE, 200_000, 'PAR-R');
    autoAllocateChargeLine($this, $student, FinanceCharge::TYPE_BHYT, 200_000, 'PAR-B');
    $payment = autoAllocatePayment($student, 600_000);

    $preview = app(PreviewManualAllocationQuery::class)->handle($payment);
    $outstanding = app(SettlementService::class)->getOutstandingLinesForStudent(
        $student->id,
        ObligationTypeRegistry::allocationPriorityOrder(),
    );

    expect(array_column($preview['candidates'], 'invoice_line_id'))
        ->toBe($outstanding->pluck('id')->map(fn ($id) => (int) $id)->all());
});

it('exposes allocation_priority_options on the settlement worklist', function () {
    $this->actingAs($this->user)
        ->get(route('finance.operations.settlement.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Finance/Operations/Settlement')
            ->has('allocation_priority_options', 8)
            ->where('allocation_priority_options.0.id', FinanceCharge::TYPE_TUITION_TERM)
            ->where('allocation_priority_options.2.id', FinanceCharge::TYPE_BHYT)
            ->where('allocation_priority_options.4.id', FinanceCharge::TYPE_RETAKE_FEE)
            ->where('allocation_priority_options.4.label', 'Phí học lại')
        );
});

function autoAllocateStudent(object $test): Student
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

function autoAllocateChargeLine(object $test, Student $student, string $type, float $amount, string $suffix): InvoiceLine
{
    $billingAccount = app(BillingAccountProvisioner::class)->forStudent((int) $student->id);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'allocation_priority',
        'source_ref' => "allocation-priority:{$student->id}:{$suffix}:{$type}",
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
        'invoice_number' => 'INV-'.$suffix.'-'.$student->id,
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

function autoAllocatePayment(Student $student, float $amount): Payment
{
    return Payment::create([
        'student_id' => $student->id,
        'amount' => $amount,
        'status' => Payment::STATUS_COMPLETED,
        'paid_at' => now(),
        'source' => 'manual',
    ]);
}
