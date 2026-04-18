<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
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

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['view_finance_payments', 'allocate_finance_payment', 'create_finance_payments']);

    app()->singleton(PermissionService::class, fn () => $permissionService);
});

function seedPaymentWithApplication(object $context): array
{
    $student = Student::factory()
        ->forCampus($context->campus)
        ->forProgram($context->program)
        ->state([
            'student_id' => 'PAY001',
            'full_name' => 'Payment Student',
            'curriculum_version_id' => $context->curriculumVersion->id,
            'intake_semester_id' => $context->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $charge = FinanceCharge::create([
        'student_id' => $student->id,
        'semester_id' => $context->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 7000000,
        'description' => 'Major tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-PAY-001',
        'student_id' => $student->id,
        'semester_id' => $context->semester->id,
        'status' => 'pending',
        'due_date' => now()->addDays(7),
        'subtotal' => 7000000,
        'discount_total' => 0,
        'total_amount' => 7000000,
        'paid_amount' => 0,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 7000000,
        'description_snapshot' => 'Major tuition',
        'status' => 'active',
    ]);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 5000000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'manual',
        'external_ref' => 'PAY-REF-001',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $context->user->id,
    ]);

    $application = PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 3000000,
        'entry_type' => 'application',
        'applied_at' => now(),
        'created_by' => $context->user->id,
    ]);

    return [$student, $payment, $application, $charge, $invoice, $line];
}

it('renders the payments index without legacy allocations relation', function () {
    [, $payment] = seedPaymentWithApplication($this);

    Payment::create([
        'student_id' => $payment->student_id,
        'amount' => 1000000,
        'method' => Payment::METHOD_CASH,
        'source' => 'manual',
        'external_ref' => 'PAY-REF-002',
        'paid_at' => now()->subDay(),
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $this->user->id,
    ]);

    $response = actingAs($this->user)->get(route('finance.payments.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/Index')
        ->has('items.data', 2)
        ->where('items.data.0.id', $payment->id)
        ->where('items.data.0.student.student_id', 'PAY001')
        ->where('items.data.0.allocated_amount', 3000000.0)
        ->where('items.data.0.unapplied_amount', 2000000.0)
        ->where('stats.payment_count', 2)
        ->where('stats.total_paid', 6000000.0)
        ->where('stats.total_applied', 3000000.0)
        ->where('stats.total_unapplied', 3000000.0)
    );
});

it('renders payment details with payment applications instead of legacy allocations', function () {
    [, $payment, $application] = seedPaymentWithApplication($this);

    $response = actingAs($this->user)->get(route('finance.payments.show', $payment->id));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/Show')
        ->where('payment.id', $payment->id)
        ->where('payment.student.student_id', 'PAY001')
        ->where('payment.allocated_amount', 3000000.0)
        ->where('payment.unapplied_amount', 2000000.0)
        ->has('payment.applications', 1)
        ->where('payment.applications.0.id', $application->id)
        ->where('payment.applications.0.entry_type', 'application')
        ->where('payment.applications.0.amount', 3000000.0)
        ->where('payment.applications.0.invoice_line.charge.description', 'Major tuition')
        ->where('payment.applications.0.invoice_line.invoice.invoice_number', 'INV-PAY-001')
    );
});

it('renders the create payment page with settlement prefill and latest dng request details', function () {
    [$student] = seedPaymentWithApplication($this);

    $dngRequest = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => 'FAUHN',
        'student_code' => $student->student_id,
        'fee_type' => 'HP',
        'description' => 'Outstanding tuition collected from settlement',
        'item_id' => 'ITEM-PAY-001',
        'amount' => 2500000,
        'status' => DngPaymentRequest::STATUS_PENDING,
    ]);

    $response = actingAs($this->user)->get(route('finance.payments.create', [
        'student_id' => $student->id,
        'amount' => 2500000,
        'fee_type' => 'HP',
        'description' => 'Outstanding tuition collected from settlement',
        'semester_id' => $this->semester->id,
        'due_date' => '2026-05-15',
        'source_context' => 'settlement_no_cash',
    ]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Finance/Payments/Create')
        ->where('prefill.student.id', $student->id)
        ->where('prefill.amount', 2500000)
        ->where('prefill.fee_type', 'HP')
        ->where('prefill.description', 'Outstanding tuition collected from settlement')
        ->where('prefill.semester_id', $this->semester->id)
        ->where('prefill.due_date', '2026-05-15')
        ->where('prefill.source_context', 'settlement_no_cash')
        ->where('prefill.dng_data.latest_dng_request.id', $dngRequest->id)
        ->where('prefill.dng_data.latest_dng_request.description', 'Outstanding tuition collected from settlement')
    );
});

it('returns dng prefill data only for students in the current campus', function () {
    [$student] = seedPaymentWithApplication($this);

    $response = actingAs($this->user)->get(route('finance.payments.student-dng-data', $student->id));

    $response->assertOk()
        ->assertJsonPath('student_id', $student->id)
        ->assertJsonPath('student_code', $student->student_id);

    $otherCampus = Campus::factory()->create();
    $otherStudent = Student::factory()
        ->forCampus($otherCampus)
        ->forProgram($this->program)
        ->state([
            'student_id' => 'PAY999',
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    actingAs($this->user)
        ->get(route('finance.payments.student-dng-data', $otherStudent->id))
        ->assertNotFound();
});
