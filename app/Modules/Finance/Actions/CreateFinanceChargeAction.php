<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Models\StudentScholarshipAward;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\InvoiceGenerationService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\ScholarshipDiscountResolver;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Debit materializer writer (ADR-0028 / wave 7).
 *
 * Sole production owner of FinanceCharge::create. Called only from
 * RequestFinanceDebitAction (intake debit path). source_type/source_id are
 * retired on new writes — source identity lives on FinanceObligation.
 */
class CreateFinanceChargeAction
{
    /**
     * Max attempts to generate a non-colliding invoice_number (FIN-13).
     */
    protected const INVOICE_NUMBER_MAX_ATTEMPTS = 5;

    public function __construct(
        protected InvoiceGenerationService $invoiceService,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
    ) {}

    /**
     * Create a new finance charge and assign it to an invoice.
     */
    public function handle(array $data): FinanceCharge
    {
        if ((float) $data['amount'] <= 0) {
            throw new \InvalidArgumentException(
                'CreateFinanceChargeAction materializes debit obligations only; use a discount or credit entitlement for reductions.',
            );
        }

        $billingAccount = $this->billingAccountProvisioner->forStudent((int) $data['student_id']);

        return $this->settlementMutationGuard->handle((int) $billingAccount->id, function () use ($data) {
            // Materialize the debit read-model row. source_type/source_id are
            // intentionally never written (wave 7 retirement).
            $charge = FinanceCharge::create([
                'finance_obligation_id' => $data['finance_obligation_id'] ?? null,
                'student_id' => $data['student_id'],
                'semester_id' => $data['semester_id'],
                'billing_cycle_id' => null,
                'charge_type' => $data['charge_type'],
                'amount' => $data['amount'],
                'description' => $data['description'],
                'effective_at' => $data['effective_at'] ?? now(),
                'status' => FinanceCharge::STATUS_ACTIVE,
                'created_by_user_id' => $data['created_by_user_id'] ?? auth()->id() ?? null,
            ]);

            // Debit materializer: project charge → invoice line. Credits never
            // materialize charge rows (ADR-0030 / wave 7).
            $dueDate = isset($data['due_date']) ? Carbon::parse($data['due_date']) : null;
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

        // FIN-04/07: cap the discount at the charge amount via the shared
        // resolver so every generation path produces the same capped number and
        // a fixed_amount/over-100% scholarship can never push balance negative.
        $discountAmount = app(ScholarshipDiscountResolver::class)
            ->resolve($scholarshipDef, (float) $tuitionCharge->amount);

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
    protected function getInvoiceForCharge(FinanceCharge $charge, ?int $invoiceId = null, ?Carbon $dueDate = null): StudentInvoice
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
    protected function findOrCreateInvoiceForCharge(FinanceCharge $charge, ?Carbon $dueDate = null): StudentInvoice
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
    protected function createInvoiceForSemester(int $studentId, int $semesterId, ?Carbon $dueDate = null): StudentInvoice
    {
        // FIN-13: invoice_number is generated from time()+random and is UNIQUE at
        // the DB level. Two invoices created in the same second for the same
        // student can collide and the insert throws. Retry on the unique
        // violation with a freshly generated number instead of bubbling a 500.
        $due = $dueDate ?? now()->addDays(30);

        for ($attempt = 1; ; $attempt++) {
            try {
                return StudentInvoice::create([
                    'invoice_number' => $this->generateInvoiceNumber($studentId, $semesterId),
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'billing_cycle_id' => null,
                    'status' => 'draft',
                    'due_date' => $due,
                ]);
            } catch (QueryException $e) {
                if (! $this->isInvoiceNumberCollision($e) || $attempt >= self::INVOICE_NUMBER_MAX_ATTEMPTS) {
                    throw $e;
                }
                // Loop and regenerate; generateInvoiceNumber re-draws the random suffix.
            }
        }
    }

    /**
     * Whether a QueryException is a unique-constraint violation on invoice_number.
     */
    protected function isInvoiceNumberCollision(QueryException $e): bool
    {
        // 23000 = integrity constraint violation (MySQL/MariaDB duplicate key).
        return $e->getCode() === '23000'
            && str_contains($e->getMessage(), 'invoice_number');
    }

    /**
     * Assign a charge to an invoice via invoice line.
     */
    protected function assignChargeToInvoice(FinanceCharge $charge, StudentInvoice $invoice): InvoiceLine
    {
        $billingAccountId = (int) $this->billingAccountProvisioner
            ->forStudent((int) $charge->student_id)
            ->id;

        return $this->settlementMutationGuard->handle($billingAccountId, function () use ($charge, $invoice): InvoiceLine {
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
        });
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
        // FIN-13: 6 random digits (re-drawn on every call) make same-second
        // collisions vanishingly unlikely and guarantee retries diverge.
        $random = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);

        return "{$prefix}-{$year}-{$studentId}-{$timestamp}{$random}";
    }
}
