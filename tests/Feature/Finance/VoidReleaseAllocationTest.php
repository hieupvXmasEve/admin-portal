<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use App\Models\User;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Actions\VoidFinanceChargeAction;
use App\Services\PermissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $this->student = Student::factory()
        ->forCampus($this->campus)
        ->forProgram($this->program)
        ->state([
            'curriculum_version_id' => $this->curriculumVersion->id,
            'intake_semester_id' => $this->semester->id,
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    session(['current_campus_id' => $this->campus->id]);
    app()->singleton('campus', fn () => $this->campus);

    $permissionService = Mockery::mock(PermissionService::class);
    $permissionService->shouldReceive('getUserPermissions')
        ->andReturn(['create_finance_charges', 'void_finance_charges', 'view_finance_charges']);
    app()->singleton(PermissionService::class, fn () => $permissionService);
});

it('void charge releases payment allocations', function () {
    $createAction = app(CreateFinanceChargeAction::class);
    $voidAction = app(VoidFinanceChargeAction::class);

    $charge = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 4',
    ]);

    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 15000000,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'charge_id' => $charge->id,
        'allocated_amount' => 15000000,
        'allocated_at' => now(),
    ]);

    expect(PaymentAllocation::count())->toBe(1);
    expect($payment->fresh()->unapplied_amount)->toBe(0.0);

    $result = $voidAction->handle($charge->id, 'Student only completed 1 level');

    expect(PaymentAllocation::count())->toBe(0);
    expect($result['released_allocations'])->toBe(1);
    expect($result['released_amount'])->toBe(15000000.0);
    expect($payment->fresh()->unapplied_amount)->toBe(15000000.0);
});

it('void charge removes invoice line and deletes empty invoice', function () {
    $createAction = app(CreateFinanceChargeAction::class);
    $voidAction = app(VoidFinanceChargeAction::class);

    $charge = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level test',
    ]);

    expect(StudentInvoice::count())->toBe(1);
    expect(InvoiceLine::count())->toBe(1);

    $voidAction->handle($charge->id, 'Test void');

    expect(InvoiceLine::count())->toBe(0);
    expect(StudentInvoice::count())->toBe(0);
});

it('void charge keeps invoice when other charges remain', function () {
    $createAction = app(CreateFinanceChargeAction::class);
    $voidAction = app(VoidFinanceChargeAction::class);

    $charge1 = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 4',
    ]);

    $charge2 = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 5',
    ]);

    expect(StudentInvoice::count())->toBe(1);
    expect(InvoiceLine::count())->toBe(2);

    $voidAction->handle($charge2->id, 'Only completed 1 level');

    expect(InvoiceLine::count())->toBe(1);
    expect(StudentInvoice::count())->toBe(1);
    expect($charge2->fresh()->status)->toBe(FinanceCharge::STATUS_VOID);
});

it('void charge recalculates invoice status', function () {
    $createAction = app(CreateFinanceChargeAction::class);
    $voidAction = app(VoidFinanceChargeAction::class);

    $charge1 = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 4',
    ]);

    $charge2 = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 5',
    ]);

    $payment = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 30000000,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'charge_id' => $charge1->id,
        'allocated_amount' => 15000000,
        'allocated_at' => now(),
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment->id,
        'charge_id' => $charge2->id,
        'allocated_amount' => 15000000,
        'allocated_at' => now(),
    ]);

    $voidAction->handle($charge2->id, 'Only completed 1 level');

    // Invoice should still be paid (charge1 = 15M, allocated = 15M)
    $invoice = StudentInvoice::first();
    expect($invoice->status)->toBe('paid');

    // Payment should have 15M unapplied
    expect($payment->fresh()->unapplied_amount)->toBe(15000000.0);
});

it('negative charge is assigned to invoice without credit memo payment', function () {
    $createAction = app(CreateFinanceChargeAction::class);

    $charge = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
        'amount' => -4500000,
        'description' => 'Scholarship discount',
    ]);

    expect((float) $charge->amount)->toBe(-4500000.0);
    expect(InvoiceLine::count())->toBe(1);
    expect(Payment::where('source', 'credit_memo')->count())->toBe(0);
});

it('e2e: void egc level and auto-allocate to tuition', function () {
    $createAction = app(CreateFinanceChargeAction::class);
    $voidAction = app(VoidFinanceChargeAction::class);
    $autoAllocateAction = app(\App\Modules\Finance\Actions\AutoAllocatePaymentsAction::class);

    // 1. Create 2 EGC levels
    $egc1 = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 4',
    ]);

    $egc2 = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
        'amount' => 15000000,
        'description' => 'EGC Level 5',
    ]);

    // 2. Pay 30M
    $payment1 = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 30000000,
        'source' => 'import',
        'paid_at' => now()->subMonth(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment1->id,
        'charge_id' => $egc1->id,
        'allocated_amount' => 15000000,
        'allocated_at' => now(),
    ]);

    PaymentAllocation::create([
        'payment_id' => $payment1->id,
        'charge_id' => $egc2->id,
        'allocated_amount' => 15000000,
        'allocated_at' => now(),
    ]);

    // 3. Void EGC Level 5
    $voidAction->handle($egc2->id, 'Student only completed 1 level, transitioning to Major');

    expect($payment1->fresh()->unapplied_amount)->toBe(15000000.0);

    // 4. Create Tuition charge (45M)
    $tuition = $createAction->handle([
        'student_id' => $this->student->id,
        'semester_id' => $this->semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 45000000,
        'description' => 'Tuition Fee',
    ]);

    // 5. Pay 25.5M more
    $payment2 = Payment::create([
        'student_id' => $this->student->id,
        'amount' => 25500000,
        'source' => 'import',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    // Before auto-allocate: payment1 has 15M unapplied, payment2 has 25.5M unapplied
    expect($payment1->fresh()->unapplied_amount)->toBe(15000000.0);
    expect($payment2->fresh()->unapplied_amount)->toBe(25500000.0);

    // Tuition invoice should exist and not be paid
    $tuitionInvoice = InvoiceLine::where('charge_id', $tuition->id)->first()->invoice;
    expect($tuitionInvoice)->not->toBeNull();
    expect($tuitionInvoice->status)->not->toBe('paid');

    // 6. Auto-allocate
    $stats = $autoAllocateAction->run(
        ['tuition_term', 'egc_level_fee'],
        $this->user->id
    );

    expect($stats['students_processed'])->toBe(1);

    // Total allocated to tuition = 15M (from payment1) + 25.5M (from payment2) = 40.5M
    $totalAllocated = (float) PaymentAllocation::where('charge_id', $tuition->id)
        ->sum('allocated_amount');

    expect($totalAllocated)->toBe(40500000.0);

    // Verify no credit memo payments exist
    expect(Payment::where('source', 'credit_memo')->count())->toBe(0);
});
