<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\BillingCycle;
use App\Models\CourseRegistration;
use App\Models\DeferCase;
use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\Semester;
use App\Models\Student;
use App\Models\StudentInvoice;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanceChargeService
{
    /**
     * Create a new finance charge and assign it to an invoice.
     */
    public function createCharge(array $data): FinanceCharge
    {
        return DB::transaction(function () use ($data) {
            // Create the charge
            $charge = FinanceCharge::create([
                'student_id' => $data['student_id'],
                'semester_id' => $data['semester_id'],
                'billing_cycle_id' => $data['billing_cycle_id'] ?? null,
                'charge_type' => $data['charge_type'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'effective_at' => $data['effective_at'] ?? now(),
                'status' => FinanceCharge::STATUS_ACTIVE,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id() ?? null,
            ]);

            // Get invoice - either from user selection or find/create
            $invoice = $this->getInvoiceForCharge($charge, $data['invoice_id'] ?? null);
            $this->assignChargeToInvoice($charge, $invoice);

            return $charge->fresh(['invoiceLines.invoice']);
        });
    }

    /**
     * Get invoice for charge - use selected invoice or find/create one.
     */
    protected function getInvoiceForCharge(FinanceCharge $charge, ?int $invoiceId = null): StudentInvoice
    {
        // If user selected an invoice, use it (but verify it's draft and matches student/semester)
        if ($invoiceId) {
            $invoice = StudentInvoice::where('id', $invoiceId)
                ->where('student_id', $charge->student_id)
                ->where('semester_id', $charge->semester_id)
                ->where('status', 'draft')
                ->first();

            if ($invoice) {
                return $invoice;
            }
        }

        // No valid invoice selected, find existing draft or create new one
        return $this->findOrCreateInvoiceForCharge($charge);
    }

    /**
     * Find or create an invoice for a charge.
     */
    protected function findOrCreateInvoiceForCharge(FinanceCharge $charge): StudentInvoice
    {
        // Find existing draft invoice for this student and semester
        $invoice = StudentInvoice::where('student_id', $charge->student_id)
            ->where('semester_id', $charge->semester_id)
            ->where('status', 'draft')
            ->first();

        if ($invoice) {
            return $invoice;
        }

        // No draft invoice found, create a new one
        return $this->createInvoiceForSemester($charge->student_id, $charge->semester_id, $charge->billing_cycle_id);
    }

    /**
     * Create a new invoice for a student in a semester.
     */
    protected function createInvoiceForSemester(int $studentId, int $semesterId, ?int $billingCycleId = null): StudentInvoice
    {
        // Find or create billing cycle
        $billingCycle = $billingCycleId
            ? BillingCycle::find($billingCycleId)
            : BillingCycle::where('semester_id', $semesterId)->first();

        if (! $billingCycle) {
            $semester = Semester::findOrFail($semesterId);
            $billingCycle = BillingCycle::create([
                'semester_id' => $semesterId,
                'name' => 'Default Billing Cycle - '.$semester->name,
                'start_date' => $semester->start_date ?? now(),
                'end_date' => $semester->end_date ?? now()->addMonths(4),
                'due_date' => $semester->end_date ?? now()->addMonths(4),
                'status' => 'active',
            ]);
        }

        // Generate invoice number
        $invoiceNumber = $this->generateInvoiceNumber($studentId, $semesterId);

        // Create invoice
        return StudentInvoice::create([
            'invoice_number' => $invoiceNumber,
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'billing_cycle_id' => $billingCycle->id,
            'status' => 'draft',
            'due_date' => $billingCycle->due_date ?? now()->addDays(30),
        ]);
    }

    /**
     * Assign a charge to an invoice via invoice line.
     */
    protected function assignChargeToInvoice(FinanceCharge $charge, StudentInvoice $invoice): InvoiceLine
    {
        $line = InvoiceLine::updateOrCreate(
            [
                'invoice_id' => $invoice->id,
                'charge_id' => $charge->id,
            ],
            [
                'amount_snapshot' => $charge->amount,
                'description_snapshot' => $charge->description,
            ]
        );

        // Recalculate invoice totals
        $this->recalculateInvoiceTotals($invoice);

        return $line;
    }

    /**
     * Recalculate invoice totals from lines.
     */
    protected function recalculateInvoiceTotals(StudentInvoice $invoice): void
    {
        $lines = $invoice->lines()->get();

        $subtotal = $lines->where('amount_snapshot', '>', 0)->sum('amount_snapshot');
        $credits = abs($lines->where('amount_snapshot', '<', 0)->sum('amount_snapshot'));
        $totalAmount = max(0, $subtotal - $credits);

        // Get paid amount from allocations
        $chargeIds = $lines->pluck('charge_id');
        $paidAmount = (float) DB::table('payment_allocations')
            ->whereIn('charge_id', $chargeIds)
            ->sum('allocated_amount');

        // Determine status
        $status = $this->determineInvoiceStatus($invoice, $totalAmount, $paidAmount);

        $invoice->update([
            'status' => $status,
        ]);
    }

    /**
     * Determine invoice status based on amounts.
     */
    protected function determineInvoiceStatus(StudentInvoice $invoice, float $totalAmount, float $paidAmount): string
    {
        // Zero amount invoices are automatically paid
        if ($totalAmount <= 0) {
            return 'paid';
        }

        if ($paidAmount >= $totalAmount) {
            return 'paid';
        }

        if ($paidAmount > 0) {
            return 'partial';
        }

        if ($invoice->due_date && $invoice->due_date->isPast()) {
            return 'overdue';
        }

        return $invoice->status === 'draft' ? 'draft' : 'pending';
    }

    /**
     * Generate a unique invoice number.
     */
    protected function generateInvoiceNumber(int $studentId, int $semesterId): string
    {
        $prefix = 'INV';
        $year = date('Y');
        $timestamp = now()->format('mdHis');
        $random = str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$studentId}-{$timestamp}{$random}";
    }

    /**
     * Void an existing charge.
     */
    public function voidCharge(int $chargeId, string $reason, ?int $userId = null): FinanceCharge
    {
        $charge = FinanceCharge::findOrFail($chargeId);

        $charge->update([
            'status' => FinanceCharge::STATUS_VOID,
            'voided_at' => now(),
            'voided_by_user_id' => $userId ?? auth()->id(),
            'void_reason' => $reason,
        ]);

        return $charge->fresh();
    }

    /**
     * Generate tuition charges for a student in a semester.
     */
    public function generateTuitionCharge(
        int $studentId,
        int $semesterId,
        float $amount,
        string $description = 'Tuition Fee',
        ?int $billingCycleId = null
    ): FinanceCharge {
        return $this->createCharge([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'billing_cycle_id' => $billingCycleId,
            'charge_type' => FinanceCharge::TYPE_TUITION_TERM,
            'amount' => $amount,
            'description' => $description,
            'effective_at' => now(),
        ]);
    }

    /**
     * Generate EGC level fee charge.
     */
    public function generateEgcLevelCharge(
        int $studentId,
        int $semesterId,
        int $level,
        float $amount
    ): FinanceCharge {
        return $this->createCharge([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'charge_type' => FinanceCharge::TYPE_EGC_LEVEL_FEE,
            'amount' => $amount,
            'description' => "EGC Level {$level} Fee",
            'effective_at' => now(),
        ]);
    }

    /**
     * Generate retake fee charge from course registration.
     */
    public function generateRetakeCharge(CourseRegistration $registration): FinanceCharge
    {
        if (! $registration->is_retake) {
            throw new \InvalidArgumentException('Registration is not marked as retake');
        }

        $amount = $registration->retake_fee ?? 0;
        $courseName = $registration->courseOffering?->curriculumUnit?->unit?->name ?? 'Unknown Course';

        return $this->createCharge([
            'student_id' => $registration->student_id,
            'semester_id' => $registration->semester_id,
            'charge_type' => FinanceCharge::TYPE_RETAKE_FEE,
            'amount' => $amount,
            'description' => "Retake Fee: {$courseName}",
            'effective_at' => now(),
            'source_type' => CourseRegistration::class,
            'source_id' => $registration->id,
        ]);
    }

    /**
     * Generate defer credit charge from a defer case.
     */
    public function generateDeferCredit(DeferCase $deferCase): ?FinanceCharge
    {
        // Only create credit for PRESERVE or PARTIAL policies
        if ($deferCase->fee_policy === DeferCase::POLICY_FORFEIT) {
            return null;
        }

        $amount = $deferCase->preserve_amount ?? 0;
        if ($amount <= 0) {
            return null;
        }

        return $this->createCharge([
            'student_id' => $deferCase->student_id,
            'semester_id' => $deferCase->semester_id,
            'charge_type' => FinanceCharge::TYPE_DEFER_CREDIT,
            'amount' => -abs($amount), // Negative for credit
            'description' => "Defer Credit ({$deferCase->fee_policy})",
            'effective_at' => $deferCase->effective_at,
            'source_type' => DeferCase::class,
            'source_id' => $deferCase->id,
            'created_by_user_id' => $deferCase->changed_by_user_id,
        ]);
    }

    /**
     * Generate scholarship credit.
     */
    public function generateScholarshipCredit(
        int $studentId,
        int $semesterId,
        float $amount,
        string $description = 'Scholarship Credit',
        $source = null
    ): FinanceCharge {
        return $this->createCharge([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'charge_type' => FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
            'amount' => -abs($amount), // Negative for credit
            'description' => $description,
            'effective_at' => now(),
            'source_type' => $source ? get_class($source) : null,
            'source_id' => $source?->id,
        ]);
    }

    /**
     * Get active charges for a student in a semester.
     */
    public function getStudentCharges(int $studentId, ?int $semesterId = null): Collection
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return $query->orderBy('effective_at')->get();
    }

    /**
     * Calculate total charges (positive amounts) for a student.
     */
    public function getTotalCharges(int $studentId, ?int $semesterId = null): float
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Calculate total credits (negative amounts) for a student.
     */
    public function getTotalCredits(int $studentId, ?int $semesterId = null): float
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '<', 0);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return abs((float) $query->sum('amount'));
    }

    /**
     * Get net amount (charges - credits) for a student.
     */
    public function getNetAmount(int $studentId, ?int $semesterId = null): float
    {
        $query = FinanceCharge::where('student_id', $studentId)
            ->where('status', FinanceCharge::STATUS_ACTIVE);

        if ($semesterId) {
            $query->where('semester_id', $semesterId);
        }

        return (float) $query->sum('amount');
    }

    /**
     * Check if a student has been charged for a semester.
     */
    public function hasActiveCharges(int $studentId, int $semesterId): bool
    {
        return FinanceCharge::where('student_id', $studentId)
            ->where('semester_id', $semesterId)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0)
            ->exists();
    }
}
