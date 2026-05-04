<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\FinanceCharge;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Services\InvoiceGenerationService;
use Illuminate\Support\Facades\DB;

class CreateFinanceChargeAction
{
    public function __construct(
        protected InvoiceGenerationService $invoiceService
    ) {}

    /**
     * Create a new finance charge and assign it to an invoice.
     */
    public function handle(array $data): FinanceCharge
    {
        return DB::transaction(function () use ($data) {
            // Create the charge
            $charge = FinanceCharge::create([
                'student_id' => $data['student_id'],
                'semester_id' => $data['semester_id'],
                'billing_cycle_id' => null,
                'charge_type' => $data['charge_type'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'effective_at' => $data['effective_at'] ?? now(),
                'status' => FinanceCharge::STATUS_ACTIVE,
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id() ?? null,
            ]);

            // All charges (positive & negative) get assigned to invoice
            $dueDate = isset($data['due_date']) ? \Carbon\Carbon::parse($data['due_date']) : null;
            $invoice = $this->getInvoiceForCharge($charge, $data['invoice_id'] ?? null, $dueDate);
            $this->assignChargeToInvoice($charge, $invoice);

            // Automatically apply scholarship if this is a tuition charge
            if ($charge->charge_type === FinanceCharge::TYPE_TUITION_TERM) {
                $this->applyScholarship($charge, $invoice);
            }

            return $charge->fresh(['invoiceLines.invoice']);
        });
    }

    /**
     * Apply scholarship to the invoice if the student has one.
     */
    protected function applyScholarship(FinanceCharge $tuitionCharge, StudentInvoice $invoice): void
    {
        $award = StudentScholarshipAward::where('student_id', $tuitionCharge->student_id)
            ->with('scholarshipDefinition')
            ->first();

        if (! $award || ! $award->scholarshipDefinition) {
            return;
        }

        // Check validity against charge effective date
        // if (! $award->scholarshipDefinition->isValid($tuitionCharge->effective_at)) {
        //     return;
        // }

        $scholarshipDef = $award->scholarshipDefinition;
        $discountAmount = 0;

        if ($scholarshipDef->type === 'percentage') {
            $discountAmount = ($tuitionCharge->amount * $scholarshipDef->amount) / 100;
        } else {
            // Default to fixed_amount
            $discountAmount = $scholarshipDef->amount;
        }

        if ($discountAmount <= 0) {
            return;
        }

        $this->invoiceService->applyInvoiceDiscount(
            $invoice,
            'scholarship',
            $discountAmount,
            StudentScholarshipAward::class,
            "Scholarship: {$scholarshipDef->name}",
            (int) $award->id,
            $tuitionCharge->created_by_user_id,
        );
    }

    /**
     * Get invoice for charge - use selected invoice or find/create one.
     */
    protected function getInvoiceForCharge(FinanceCharge $charge, ?int $invoiceId = null, ?\Carbon\Carbon $dueDate = null): StudentInvoice
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
        return $this->findOrCreateInvoiceForCharge($charge, $dueDate);
    }

    /**
     * Find or create an invoice for a charge.
     */
    protected function findOrCreateInvoiceForCharge(FinanceCharge $charge, ?\Carbon\Carbon $dueDate = null): StudentInvoice
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
        return $this->createInvoiceForSemester($charge->student_id, $charge->semester_id, $dueDate);
    }

    /**
     * Create a new invoice for a student in a semester.
     */
    protected function createInvoiceForSemester(int $studentId, int $semesterId, ?\Carbon\Carbon $dueDate = null): StudentInvoice
    {
        // Generate invoice number
        $invoiceNumber = $this->generateInvoiceNumber($studentId, $semesterId);

        // Create invoice
        return StudentInvoice::create([
            'invoice_number' => $invoiceNumber,
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'billing_cycle_id' => null,
            'status' => 'draft',
            'due_date' => $dueDate ?? now()->addDays(30),
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
        $this->invoiceService->updateInvoiceStatus($invoice);
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
}
