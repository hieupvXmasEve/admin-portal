<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceObligation;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;

/**
 * Shared billing fixtures for the Revenue report tests (query + HTTP surface).
 * Not a test file itself — required via require_once, same convention as
 * tests/Feature/Finance/Batch/helpers.php.
 */
function revStudent(Campus $campus, Semester $semester, string $code): Student
{
    return Student::factory()->forCampus($campus)->create([
        'student_id' => $code,
        'status' => 'intake_course',
        'intake' => 1,
        'intake_mode' => 'sequential',
        'intake_semester_id' => $semester->id,
    ]);
}

/**
 * @return array{0: StudentInvoice, 1: InvoiceLine}
 */
function revBill(Student $student, Semester $semester, float $amount, string $status = 'pending', bool $recalculate = true): array
{
    $billingAccount = BillingAccount::query()->firstOrCreate(['student_id' => $student->id]);
    $invoice = StudentInvoice::create([
        'invoice_number' => 'INV-REV-'.$student->id.'-'.uniqid(),
        'student_id' => $student->id,
        'semester_id' => $semester->id,
        'status' => $status,
        'due_date' => now()->addDays(30),
    ]);

    $obligation = FinanceObligation::create([
        'billing_account_id' => $billingAccount->id,
        'source_system' => 'academic',
        'source_kind' => 'revenue_test',
        'source_ref' => 'revenue:'.uniqid('', true),
        'obligation_type' => FinanceCharge::TYPE_TUITION_TERM,
        'lifecycle_status' => FinanceObligation::STATUS_ACCEPTED,
        'amount' => $amount,
        'currency' => 'VND',
        'pricing_rule_version' => 'revenue_test',
        'pricing_snapshot' => [],
        'accepted_at' => now(),
    ]);

    $charge = FinanceCharge::create([
        'finance_obligation_id' => $obligation->id,
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

    if ($recalculate) {
        // SettlementService refuses to cache a snapshot from an invalid position
        // (e.g. a gross<=0 line), so callers building a deliberately invalid
        // line skip this — same pattern as the CP-INVALID fixture in
        // CollectionProgressViewTest.
        app(SettlementService::class)->recalculateInvoiceSnapshot($invoice);
    }

    return [$invoice->fresh(), $line];
}

function revPay(Student $student, InvoiceLine $line, float $paymentAmount, ?float $applyAmount = null): Payment
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

/** @return array<string, mixed>|null */
function revRowFor(array $result, int $semesterId): ?array
{
    return collect($result['rows'])->firstWhere('semester_id', $semesterId);
}
