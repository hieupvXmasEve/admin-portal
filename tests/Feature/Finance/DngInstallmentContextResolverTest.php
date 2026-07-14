<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Actions\CreateFinanceChargeAction;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Support\DngInstallmentContextResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('uses the canonical settlement position for an installment reminder balance', function (): void {
    $user = User::factory()->create();
    $campus = Campus::factory()->create();
    $semester = Semester::factory()->create();
    $student = Student::factory()->forCampus($campus)->create([
        'status' => 'intake_course',
        'intake' => 2024,
        'intake_semester_id' => $semester->id,
    ]);
    $obligation = FinanceObligation::query()->create([
        'source_system' => 'test',
        'source_kind' => 'installment_reminder',
        'source_ref' => 'installment-reminder:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => '1000000.00',
        'currency' => 'VND',
        'pricing_rule_version' => 'test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);
    $charge = app(CreateFinanceChargeAction::class)->handle([
        'finance_obligation_id' => $obligation->id,
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
        'amount' => '1000000.00',
        'description' => 'Installment reminder charge',
        'created_by_user_id' => $user->id,
    ]);
    $line = InvoiceLine::query()->where('charge_id', $charge->id)->firstOrFail();
    $dngRequest = DngPaymentRequest::query()->create([
        'student_id' => $student->id,
        'campus_code' => 'TEST',
        'student_code' => $student->student_id,
        'item_id' => 'INSTALLMENT-REMINDER',
        'fee_type' => 'tuition',
        'description' => 'Installment reminder',
        'semester_id' => $semester->id,
        'due_date' => now()->addWeek(),
        'amount' => '500000.00',
        'status' => DngPaymentRequest::STATUS_PUSHED_TO_DNG,
    ]);

    FinanceChargeInstallment::query()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 1,
        'amount' => '500000.00',
        'due_date' => now()->addWeek()->toDateString(),
        'status' => FinanceChargeInstallment::STATUS_AWAITING_PAYMENT,
        'dng_payment_request_id' => $dngRequest->id,
    ]);
    FinanceChargeInstallment::query()->create([
        'finance_charge_id' => $charge->id,
        'installment_no' => 2,
        'amount' => '500000.00',
        'due_date' => now()->addWeeks(2)->toDateString(),
    ]);
    $payment = Payment::query()->create([
        'student_id' => $student->id,
        'amount' => '400000.00',
        'method' => Payment::METHOD_CASH,
        'source' => 'test',
        'paid_at' => now(),
        'status' => Payment::STATUS_COMPLETED,
        'received_by_user_id' => $user->id,
    ]);
    PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'invoice_line_id' => $line->id,
        'amount' => '400000.00',
        'entry_type' => 'application',
        'applied_at' => now(),
        'created_by' => $user->id,
    ]);
    $dngRequest->reservationTargets()->create([
        'invoice_line_id' => $line->id,
        'captured_collectible' => '500000.00',
        'target_identity' => 'invoice_line:'.$line->id,
    ]);

    $resolver = app(DngInstallmentContextResolver::class);
    $context = $resolver->resolve($dngRequest);
    $balance = $resolver->currentBalance($dngRequest);

    expect($context)->toMatchArray([
        'installment_no' => 1,
        'installment_total' => 2,
        'installment_amount_formatted' => '500.000',
        'remaining_balance_formatted' => '600.000',
    ])
        ->and($balance)->toBe([
            'amount' => 600_000.0,
            'issue_codes' => [],
        ]);
});
