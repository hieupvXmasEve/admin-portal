<?php

declare(strict_types=1);

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Actions\ReconcileChargeInstallmentsAction;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\SettlementMutationGuard;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceGenerationService
{
    public function __construct(
        protected SettlementService $settlementService,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementMutationGuard $settlementMutationGuard,
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
        $billingAccountId = (int) $this->billingAccountProvisioner->forStudent($studentId)->id;

        $invoice = $this->settlementMutationGuard->handleIfChanged($billingAccountId, function ($_billingAccount, \Closure $markChanged) use ($studentId, $semesterId, $billingCycleId, $dueDate): StudentInvoice {
            $invoice = StudentInvoice::firstOrNew([
                'student_id' => $studentId,
                'semester_id' => $semesterId,
                'billing_cycle_id' => $billingCycleId,
            ]);

            if (! $invoice->exists) {
                $invoice->invoice_number = $this->generateInvoiceNumber($studentId, $semesterId);
                $invoice->status = 'draft';
                $invoice->due_date = $dueDate ?? now()->addDays(30);
                $invoice->save();
                $markChanged();
            }

            $this->refreshInvoiceFromCharges($invoice);

            return $invoice;
        });

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

        $billingAccountId = (int) $this->billingAccountProvisioner
            ->forStudent((int) $invoice->student_id)
            ->id;

        $this->settlementMutationGuard->handleIfChanged($billingAccountId, function ($_billingAccount, \Closure $markChanged) use ($invoice, $charges): void {
            DB::transaction(function () use ($invoice, $charges, $markChanged): void {
                // Get existing line charge IDs
                $existingChargeIds = $invoice->invoiceLines()->pluck('charge_id')->toArray();
                $newChargeIds = $charges->pluck('id')->toArray();

                // Remove lines for charges no longer active
                $toRemove = array_diff($existingChargeIds, $newChargeIds);
                $removedLineCount = InvoiceLine::where('invoice_id', $invoice->id)
                    ->whereIn('charge_id', $toRemove)
                    ->update([
                        'status' => 'void',
                        'voided_at' => now(),
                        'void_reason' => 'Charge no longer active during invoice refresh',
                    ]);
                if ($removedLineCount > 0) {
                    $markChanged();
                }

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

                    if ($line->wasRecentlyCreated) {
                        $markChanged();
                    }

                    // A line previously voided (e.g. the charge was temporarily
                    // inactive) may be reactivated, but only its lifecycle status is
                    // restored — the frozen amount_snapshot stays untouched.
                    if (! $line->wasRecentlyCreated && $line->status !== 'active') {
                        $line->update([
                            'status' => 'active',
                            'voided_at' => null,
                            'void_reason' => null,
                        ]);
                        $markChanged();
                    }
                }

                // Recalculate totals
                $this->recalculateInvoiceTotals($invoice);
            });
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
        $billingAccountId = (int) $this->billingAccountProvisioner->forStudent((int) $invoice->student_id)->id;
        $this->settlementMutationGuard->handleIfChanged($billingAccountId, function ($_billingAccount, \Closure $markChanged) use ($invoice): void {
            if ($invoice->status !== 'draft') {
                return;
            }

            $this->refreshInvoiceFromCharges($invoice);

            if ($invoice->total_amount <= 0) {
                $invoice->update(['status' => 'paid']);
            } else {
                $invoice->update(['status' => 'pending']);
            }
            $markChanged();
        });
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
        // FIN-09: the discount write and the installment reconcile must be atomic.
        // Reconcile can throw (committed installments already exceed the new net
        // due) and that "block" must leave NO discount/allocation behind — even
        // when the caller is not already inside a transaction.
        return DB::transaction(function () use (
            $invoice,
            $discountType,
            $amount,
            $discountSource,
            $description,
            $referenceId,
            $approvedBy,
        ) {
            $discount = $this->settlementService->createOrRefreshInvoiceDiscount(
                $invoice,
                $discountType,
                $amount,
                $discountSource,
                $description,
                $referenceId,
                $approvedBy,
            );

            // A discount changes net due. Any charge on this invoice already split
            // into installments must have its pending rows reconciled to the new
            // net due (or surface for review if committed rows already exceed it).
            $this->reconcileInstallmentsForInvoice($invoice);

            return $discount;
        });
    }

    /**
     * Reconcile installment plans for every active charge on the invoice that has
     * an installment plan (FIN-09). No-op for the common case where charges have
     * no installments yet (discount applied during generation, before any split).
     */
    private function reconcileInstallmentsForInvoice(StudentInvoice $invoice): void
    {
        $chargeIds = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', 'active')
            ->pluck('charge_id')
            ->filter()
            ->unique();

        if ($chargeIds->isEmpty()) {
            return;
        }

        $chargeIdsWithPlan = FinanceChargeInstallment::query()
            ->whereIn('finance_charge_id', $chargeIds)
            ->distinct()
            ->pluck('finance_charge_id');

        if ($chargeIdsWithPlan->isEmpty()) {
            return;
        }

        $reconciler = app(ReconcileChargeInstallmentsAction::class);

        FinanceCharge::query()
            ->whereIn('id', $chargeIdsWithPlan)
            ->where('status', FinanceCharge::STATUS_ACTIVE)
            ->get()
            ->each(fn (FinanceCharge $charge) => $reconciler->handle($charge));
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
