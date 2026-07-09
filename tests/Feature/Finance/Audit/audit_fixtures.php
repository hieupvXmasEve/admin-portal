<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\Program;
use App\Models\Semester;
use App\Models\Student;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Dng\Models\DngPaymentRequestCharge;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Models\PaymentApplication;
use App\Modules\Finance\Models\StudentInvoice;

/**
 * Shared fixtures for the audit read-layer tests (graph query + warning builder).
 * Guarded with function_exists so multiple test files can require_once this.
 */
if (! function_exists('auditFixtureStudent')) {
    function auditFixtureStudent(): Student
    {
        $semester = Semester::factory()->create();

        return Student::factory()
            ->forCampus(Campus::factory()->create())
            ->forProgram(Program::factory()->create())
            ->state(['intake' => 1, 'intake_semester_id' => $semester->id])
            ->create();
    }
}

if (! function_exists('seedFanOutFixture')) {
    /**
     * One student, one invoice with 2 active lines (2 charges), one payment applied
     * across BOTH lines, one DNG request linked to BOTH charges via the pivot.
     *
     * @return array{0:Student,1:StudentInvoice,2:list<InvoiceLine>,3:Payment,4:DngPaymentRequest,5:list<FinanceCharge>}
     */
    function seedFanOutFixture(): array
    {
        $student = auditFixtureStudent();
        $semesterId = (int) $student->intake_semester_id;

        $invoice = StudentInvoice::create([
            'invoice_number' => 'INV-FANOUT-'.$student->id,
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'status' => 'partial',
            'due_date' => now()->addDays(30),
            'subtotal' => 3000,
            'discount_total' => 0,
            'total_amount' => 3000,
            'paid_amount' => 1500,
            'cached_total_amount' => 3000,
            'cached_paid_amount' => 1500,
        ]);

        $charges = [];
        $lines = [];
        foreach ([0, 1] as $i) {
            $charge = FinanceCharge::create([
                'student_id' => $student->id,
                'semester_id' => $semesterId,
                'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
                'amount' => 1500,
                'description' => "Fan-out charge {$i}",
                'effective_at' => now(),
                'status' => FinanceCharge::STATUS_ACTIVE,
            ]);
            $lines[] = InvoiceLine::create([
                'invoice_id' => $invoice->id,
                'charge_id' => $charge->id,
                'amount_snapshot' => 1500,
                'description_snapshot' => "Fan-out charge {$i}",
                'status' => 'active',
            ]);
            $charges[] = $charge;
        }

        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => 1500,
            'method' => Payment::METHOD_GATEWAY,
            'source' => 'manual',
            'paid_at' => now(),
            'status' => Payment::STATUS_COMPLETED,
        ]);
        foreach ($lines as $line) {
            PaymentApplication::create([
                'payment_id' => $payment->id,
                'invoice_line_id' => $line->id,
                'amount' => 750,
                'entry_type' => 'application',
                'applied_at' => now(),
            ]);
        }

        $dng = DngPaymentRequest::create([
            'student_id' => $student->id,
            'campus_code' => 'HCM',
            'student_code' => (string) $student->student_id,
            'fee_type' => 'HL',
            'item_id' => 'ITEM-FANOUT-'.$student->id,
            'amount' => 3000,
            'status' => 'pending',
        ]);
        foreach ($charges as $charge) {
            DngPaymentRequestCharge::create([
                'dng_payment_request_id' => $dng->id,
                'finance_charge_id' => $charge->id,
                'amount' => 1500,
            ]);
        }

        return [$student, $invoice, $lines, $payment, $dng, $charges];
    }
}

if (! function_exists('seedStaleCacheFixture')) {
    /**
     * An invoice whose cached_paid_amount is deliberately stale (0) while the real
     * applied amount is 500 — so SettlementService-derived paid disagrees with cache.
     *
     * @return array{0:Student,1:StudentInvoice}
     */
    function seedStaleCacheFixture(): array
    {
        $student = auditFixtureStudent();
        $semesterId = (int) $student->intake_semester_id;

        $invoice = StudentInvoice::create([
            'invoice_number' => 'INV-STALE-'.$student->id,
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'status' => 'partial',
            'due_date' => now()->addDays(30),
            'subtotal' => 1000,
            'discount_total' => 0,
            'total_amount' => 1000,
            'paid_amount' => 0,
        ]);

        $charge = FinanceCharge::create([
            'student_id' => $student->id,
            'semester_id' => $semesterId,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => 1000,
            'description' => 'Stale-cache charge',
            'effective_at' => now(),
            'status' => FinanceCharge::STATUS_ACTIVE,
        ]);
        $line = InvoiceLine::create([
            'invoice_id' => $invoice->id,
            'charge_id' => $charge->id,
            'amount_snapshot' => 1000,
            'description_snapshot' => 'Stale-cache charge',
            'status' => 'active',
        ]);

        $payment = Payment::create([
            'student_id' => $student->id,
            'amount' => 500,
            'method' => Payment::METHOD_GATEWAY,
            'source' => 'manual',
            'paid_at' => now(),
            'status' => Payment::STATUS_COMPLETED,
        ]);
        PaymentApplication::create([
            'payment_id' => $payment->id,
            'invoice_line_id' => $line->id,
            'amount' => 500,
            'entry_type' => 'application',
            'applied_at' => now(),
        ]);

        // Force the cache stale AFTER the application so any recalculation is overwritten.
        $invoice->forceFill(['cached_total_amount' => 1000, 'cached_paid_amount' => 0])->save();

        return [$student, $invoice->fresh()];
    }
}
