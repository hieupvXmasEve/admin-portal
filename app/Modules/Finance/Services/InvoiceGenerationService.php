<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Models\FinanceCharge;
use App\Models\InvoiceDiscount;
use App\Models\InvoiceLine;
use App\Models\StudentInvoice;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceGenerationService
{
    public function __construct(
        protected SettlementService $settlementService
    ) {}

    /**
     * Generate or update an invoice for a student in a semester.
     */
    public function generateInvoice(
        int $studentId,
        int $semesterId,
        ?int $billingCycleId = null,
        ?Carbon $dueDate = null
    ): StudentInvoice {
        // Find existing invoice or create new
        $invoice = StudentInvoice::firstOrNew([
            'student_id' => $studentId,
            'semester_id' => $semesterId,
            'billing_cycle_id' => $billingCycleId,
        ]);

        // Generate invoice number if new
        if (! $invoice->exists) {
            $invoice->invoice_number = $this->generateInvoiceNumber($studentId, $semesterId);
            $invoice->status = 'draft';
            $invoice->due_date = $dueDate ?? now()->addDays(30); // Use provided due_date or default 30 days
            $invoice->save();
        }

        // Refresh invoice lines from charges
        $this->refreshInvoiceFromCharges($invoice);

        return $invoice->fresh();
    }

    /**
     * Refresh invoice lines from active charges.
     */
    public function refreshInvoiceFromCharges(StudentInvoice $invoice): StudentInvoice
    {
        $charges = FinanceCharge::where('student_id', $invoice->student_id)
            ->where('semester_id', $invoice->semester_id)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->where('amount', '>', 0)
            ->when($invoice->billing_cycle_id, function ($query) use ($invoice) {
                return $query->where(function ($q) use ($invoice) {
                    $q->where('billing_cycle_id', $invoice->billing_cycle_id)
                        ->orWhereNull('billing_cycle_id');
                });
            })
            ->get();

        DB::transaction(function () use ($invoice, $charges) {
            // Get existing line charge IDs
            $existingChargeIds = $invoice->invoiceLines()->pluck('charge_id')->toArray();
            $newChargeIds = $charges->pluck('id')->toArray();

            // Remove lines for charges no longer active
            $toRemove = array_diff($existingChargeIds, $newChargeIds);
            InvoiceLine::where('invoice_id', $invoice->id)
                ->whereIn('charge_id', $toRemove)
                ->update([
                    'status' => 'void',
                    'voided_at' => now(),
                    'void_reason' => 'Charge no longer active during invoice refresh',
                ]);

            // Add lines for new charges. DB-01 / NT2: amount_snapshot and
            // description_snapshot are frozen at creation — a refresh never
            // overwrites an existing line's money snapshot. A changed charge
            // amount is handled by void + recreate, not by restating history.
            foreach ($charges as $charge) {
                $line = InvoiceLine::firstOrCreate(
                    [
                        'invoice_id' => $invoice->id,
                        'charge_id' => $charge->id,
                    ],
                    [
                        'amount_snapshot' => $charge->amount,
                        'description_snapshot' => $charge->description,
                        'status' => 'active',
                        'voided_at' => null,
                        'void_reason' => null,
                    ]
                );

                // A line previously voided (e.g. the charge was temporarily
                // inactive) may be reactivated, but only its lifecycle status is
                // restored — the frozen amount_snapshot stays untouched.
                if (! $line->wasRecentlyCreated && $line->status !== 'active') {
                    $line->update([
                        'status' => 'active',
                        'voided_at' => null,
                        'void_reason' => null,
                    ]);
                }
            }

            // Recalculate totals
            $this->recalculateInvoiceTotals($invoice);
        });

        return $invoice->fresh();
    }

    /**
     * Update invoice status based on current allocations.
     * Public method for updating invoice status after payment allocations.
     */
    public function updateInvoiceStatus(StudentInvoice $invoice): void
    {
        $this->recalculateInvoiceTotals($invoice);
    }

    /**
     * Recalculate invoice totals from lines.
     */
    protected function recalculateInvoiceTotals(StudentInvoice $invoice): void
    {
        $this->settlementService->recalculateInvoiceSnapshot($invoice);
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
     * Finalize/close an invoice (change from draft to pending).
     */
    public function closeInvoice(StudentInvoice $invoice): void
    {
        if ($invoice->status === 'draft') {
            $this->refreshInvoiceFromCharges($invoice);

            if ($invoice->total_amount <= 0) {
                $invoice->update(['status' => 'paid']);
            } else {
                $invoice->update(['status' => 'pending']);
            }
        }
    }

    public function applyInvoiceDiscount(
        StudentInvoice $invoice,
        string $discountType,
        float $amount,
        ?string $discountSource = null,
        ?string $description = null,
        ?int $referenceId = null,
        ?int $approvedBy = null,
    ): InvoiceDiscount {
        return $this->settlementService->createOrRefreshInvoiceDiscount(
            $invoice,
            $discountType,
            $amount,
            $discountSource,
            $description,
            $referenceId,
            $approvedBy,
        );
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
     * Generate invoices for multiple students in a semester.
     */
    public function bulkGenerateInvoices(array $studentIds, int $semesterId, ?int $billingCycleId = null, ?Carbon $dueDate = null): array
    {
        $results = [];

        foreach ($studentIds as $studentId) {
            try {
                $invoice = $this->generateInvoice($studentId, $semesterId, $billingCycleId, $dueDate);
                $results[$studentId] = [
                    'success' => true,
                    'invoice_id' => $invoice->id,
                    'invoice_number' => $invoice->invoice_number,
                ];
            } catch (\Exception $e) {
                $results[$studentId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
