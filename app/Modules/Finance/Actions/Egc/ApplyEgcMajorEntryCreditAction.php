<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions\Egc;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ApplyEgcMajorEntryCreditAction
{
    public const CREDIT_AMOUNT = -15_000_000;

    /**
     * Apply -15M EGC exempt credit to a transitioned student's invoice.
     */
    public static function run(int $studentId, int $semesterId): FinanceCharge
    {
        return DB::transaction(function () use ($studentId, $semesterId) {
            $student = Student::findOrFail($studentId);

            // Guard: student must have transitioned out of intake_pre_uni_gc
            if ($student->status === 'intake_pre_uni_gc') {
                throw ValidationException::withMessages([
                    'student_id' => ['Student is still an active EGC student and has not transitioned to major entry.'],
                ]);
            }

            // Guard: student must have an existing invoice for this semester
            $invoice = StudentInvoice::where('student_id', $studentId)
                ->where('semester_id', $semesterId)
                ->first();

            if (! $invoice) {
                throw ValidationException::withMessages([
                    'semester_id' => ['No invoice found for this student in the selected semester. Cannot apply credit.'],
                ]);
            }

            // Guard: double-apply check
            $existing = FinanceCharge::where('student_id', $studentId)
                ->where('semester_id', $semesterId)
                ->where('charge_type', FinanceCharge::TYPE_EGC_EXEMPT_CREDIT)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'student_id' => ['An EGC exempt credit has already been applied for this student and semester.'],
                ]);
            }

            // Create the credit charge
            $charge = FinanceCharge::create([
                'student_id' => $studentId,
                'semester_id' => $semesterId,
                'charge_type' => FinanceCharge::TYPE_EGC_EXEMPT_CREDIT,
                'amount' => self::CREDIT_AMOUNT,
                'description' => 'EGC Early Major Entry Credit',
                'effective_at' => now(),
                'status' => FinanceCharge::STATUS_ACTIVE,
                'created_by_user_id' => auth()->id(),
            ]);

            // Assign to invoice line
            InvoiceLine::updateOrCreate(
                ['invoice_id' => $invoice->id, 'charge_id' => $charge->id],
                ['amount_snapshot' => $charge->amount, 'description_snapshot' => $charge->description]
            );

            // Recalculate invoice totals
            $invoice->recalculateTotals();

            return $charge;
        });
    }
}
