<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\CurriculumVersion;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Queries\GetStudentBalanceQuery;
use App\Modules\Finance\Queries\Operations\ListDueInvoicesQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('derives invoice status and related finance reads from line-level settlement instead of stale snapshot fields', function () {
    $campus = Campus::factory()->create();
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
            'curriculum_version_id' => $curriculumVersion->id,
            'intake_semester_id' => $semester->id,
            'status' => 'intake_course',
            'intake' => 1,
            'intake_mode' => 'sequential',
        ])
        ->create();

    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-DRIFT-1',
        'student_id' => $student->id,
        'billing_cycle_id' => null,
        'semester_id' => $semester->id,
        'status' => 'pending',
        'due_date' => now()->subDay(),
        'subtotal' => 0,
        'discount_total' => 0,
        'total_amount' => 999999,
        'paid_amount' => 0,
    ]);

    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $obligation = FinanceObligation::query()->create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'test',
        'source_kind' => 'derived_snapshot',
        'source_ref' => 'derived-snapshot:'.$student->id,
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => 10_000_000,
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => 10000000,
        'description' => 'Major tuition',
        'effective_at' => now(),
        'status' => FinanceCharge::STATUS_ACTIVE,
    ]);

    $line = InvoiceLine::create([
        'invoice_id' => $invoice->id,
        'charge_id' => $charge->id,
        'amount_snapshot' => 10000000,
        'description_snapshot' => 'Major tuition',
        'status' => 'active',
    ]);

    $discount = InvoiceDiscount::create([
        'invoice_id' => $invoice->id,
        'discount_type' => 'voucher',
        'discount_source' => 'App\\Models\\VoucherApplication',
        'description' => 'Voucher Applied',
        'amount' => 2000000,
        'reference_id' => 1,
    ]);

    DiscountAllocation::create([
        'invoice_discount_id' => $discount->id,
        'invoice_line_id' => $line->id,
        'amount' => 2000000,
        'entry_type' => 'allocation',
        'allocation_rule' => 'oldest_line_first',
    ]);

    $payment = Payment::create([
        'student_id' => $student->id,
        'amount' => 8000000,
        'method' => Payment::METHOD_BANK_TRANSFER,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
    ]);

    PaymentApplication::create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => 8000000,
        'entry_type' => 'application',
        'applied_at' => now(),
    ]);

    expect($invoice->fresh()->total_amount)->toBe(8000000.0)
        ->and($invoice->fresh()->paid_amount)->toBe(8000000.0)
        ->and($invoice->fresh()->outstanding_balance)->toBe(0.0)
        ->and($invoice->fresh()->real_time_status)->toBe('paid');

    expect(StudentInvoice::query()->filterByStatus('paid')->pluck('id')->all())->toContain($invoice->id)
        ->and(StudentInvoice::query()->filterByStatus('overdue')->pluck('id')->all())->not->toContain($invoice->id);

    $balance = app(GetStudentBalanceQuery::class)->handle($student->id, $semester->id);

    expect($balance['total_charges'])->toBe(10000000.0)
        ->and($balance['total_credits'])->toBe(2000000.0)
        ->and($balance['net_charges'])->toBe(8000000.0)
        ->and($balance['total_paid'])->toBe(8000000.0)
        ->and($balance['balance'])->toBe(0.0);

    app()->instance('campus', $campus);

    $dueInvoices = app(ListDueInvoicesQuery::class)->handle($semester->id, null, null);
    $row = collect($dueInvoices->items())->firstWhere('invoice_number', 'INV-DRIFT-1');

    expect($row['total_amount'])->toBe(8000000.0)
        ->and($row['paid_amount'])->toBe(8000000.0)
        ->and((float) $row['balance'])->toBe(0.0);
});
