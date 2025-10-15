<?php

namespace App\Services;

use App\Models\InvoiceItem;
use App\Models\StudentInvoice;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Exception;

class InvoicePaymentService
{
    private const ITEM_PRIORITY = [
        'tuition' => 1,
        'egc' => 2,
        'retake' => 3,
        'miscellaneous' => 4,
    ];

    public function __construct(
        private CashWalletService $cashWalletService
    ) {}

    /**
     * Pay invoice from student's cash wallet with priority-based item payment.
     *
     * @param StudentInvoice $invoice
     * @return array
     * @throws Exception
     */
    public function payInvoiceFromWallet(StudentInvoice $invoice): array
    {
        // Validate invoice can be paid
        if ($invoice->status === 'cancelled') {
            throw new Exception('Cannot pay cancelled invoice.');
        }

        if ($invoice->isPaid()) {
            throw new Exception('Invoice is already fully paid.');
        }

        return DB::transaction(function () use ($invoice) {
            // Get or create student's wallet
            $wallet = $this->cashWalletService->getOrCreateWallet($invoice->student_id);
            $availableBalance = (float) $wallet->balance;

            if ($availableBalance <= 0) {
                return [
                    'success' => false,
                    'amount_paid' => 0,
                    'remaining_balance' => 0,
                    'paid_items' => [],
                    'partial_items' => [],
                    'unpaid_items' => $this->getPayableItems($invoice)->toArray(),
                    'message' => 'Insufficient wallet balance. Current balance: 0 VND',
                ];
            }

            // Get items that need payment, sorted by priority
            $payableItems = $this->getPayableItems($invoice);

            if ($payableItems->isEmpty()) {
                return [
                    'success' => false,
                    'amount_paid' => 0,
                    'remaining_balance' => $availableBalance,
                    'paid_items' => [],
                    'partial_items' => [],
                    'unpaid_items' => [],
                    'message' => 'No items require payment.',
                ];
            }

            // Calculate invoice outstanding (respect discount)
            $invoiceOutstanding = $invoice->total_amount - $invoice->paid_amount;
            
            // Maximum amount we can pay is the minimum of wallet balance and invoice outstanding
            $maxPayableAmount = min($availableBalance, $invoiceOutstanding);
            
            if ($maxPayableAmount <= 0) {
                return [
                    'success' => false,
                    'amount_paid' => 0,
                    'remaining_balance' => $availableBalance,
                    'paid_items' => [],
                    'partial_items' => [],
                    'unpaid_items' => [],
                    'message' => 'Invoice is already fully paid or no outstanding amount.',
                ];
            }

            $totalAmountPaid = 0;
            $paidItems = [];
            $partialItems = [];
            $unpaidItems = [];
            $currentBalance = $maxPayableAmount; // Use capped amount

            // Process each item in priority order
            foreach ($payableItems as $item) {
                // Stop if we've paid enough to cover the invoice total (after discount)
                if ($totalAmountPaid >= $maxPayableAmount) {
                    $unpaidItems[] = [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'description' => $item->description,
                        'total_price' => (float) $item->total_price,
                        'paid_amount' => (float) $item->paid_amount,
                        'remaining_amount' => $item->total_price - $item->paid_amount,
                    ];
                    continue;
                }

                $remainingAmount = $item->total_price - $item->paid_amount;

                if ($currentBalance <= 0) {
                    // No balance left, mark as unpaid
                    $unpaidItems[] = [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'description' => $item->description,
                        'total_price' => (float) $item->total_price,
                        'paid_amount' => (float) $item->paid_amount,
                        'remaining_amount' => $remainingAmount,
                    ];
                    continue;
                }

                // Calculate how much we can pay for this item without exceeding invoice total
                $maxForThisItem = min($remainingAmount, $currentBalance, $maxPayableAmount - $totalAmountPaid);

                if ($maxForThisItem >= $remainingAmount) {
                    // Can pay full remaining amount of this item
                    $amountToPay = $remainingAmount;
                    $item->paid_amount += $amountToPay;
                    $item->save();

                    $currentBalance -= $amountToPay;
                    $totalAmountPaid += $amountToPay;

                    $paidItems[] = [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'description' => $item->description,
                        'total_price' => (float) $item->total_price,
                        'paid_amount' => (float) $item->paid_amount,
                        'remaining_amount' => 0,
                        'amount_paid_now' => $amountToPay,
                    ];
                } else {
                    // Can only pay partial
                    $amountToPay = $maxForThisItem;
                    $item->paid_amount += $amountToPay;
                    $item->save();

                    $totalAmountPaid += $amountToPay;
                    $currentBalance -= $amountToPay;

                    $partialItems[] = [
                        'id' => $item->id,
                        'item_type' => $item->item_type,
                        'description' => $item->description,
                        'total_price' => (float) $item->total_price,
                        'paid_amount' => (float) $item->paid_amount,
                        'remaining_amount' => $item->total_price - $item->paid_amount,
                        'amount_paid_now' => $amountToPay,
                    ];
                }
            }

            // Create wallet transaction if any payment was made
            if ($totalAmountPaid > 0) {
                $this->cashWalletService->withdraw(
                    $wallet->id,
                    $totalAmountPaid,
                    "Payment for invoice {$invoice->invoice_number}",
                    StudentInvoice::class,
                    $invoice->id
                );

                // Update invoice totals and status
                $invoice->recalculateTotals();
            }

            $message = $this->generatePaymentMessage($totalAmountPaid, $paidItems, $partialItems, $unpaidItems);

            return [
                'success' => true,
                'amount_paid' => $totalAmountPaid,
                'remaining_balance' => $currentBalance,
                'paid_items' => $paidItems,
                'partial_items' => $partialItems,
                'unpaid_items' => $unpaidItems,
                'message' => $message,
            ];
        });
    }

    /**
     * Get items that need payment, sorted by priority.
     *
     * @param StudentInvoice $invoice
     * @return Collection
     */
    public function getPayableItems(StudentInvoice $invoice): Collection
    {
        return $invoice->items()
            ->whereRaw('paid_amount < total_price')
            ->get()
            ->sortBy(function ($item) {
                return [
                    self::ITEM_PRIORITY[$item->item_type] ?? 999,
                    $item->created_at,
                ];
            })
            ->values();
    }

    /**
     * Calculate payment preview without executing.
     *
     * @param StudentInvoice $invoice
     * @param float $availableBalance
     * @return array
     */
    public function calculatePaymentPreview(StudentInvoice $invoice, float $availableBalance): array
    {
        if ($availableBalance <= 0) {
            return [
                'success' => false,
                'amount_paid' => 0,
                'remaining_balance' => 0,
                'paid_items' => [],
                'partial_items' => [],
                'unpaid_items' => $this->getPayableItems($invoice)->toArray(),
                'message' => 'Insufficient wallet balance.',
            ];
        }

        $payableItems = $this->getPayableItems($invoice);

        if ($payableItems->isEmpty()) {
            return [
                'success' => false,
                'amount_paid' => 0,
                'remaining_balance' => $availableBalance,
                'paid_items' => [],
                'partial_items' => [],
                'unpaid_items' => [],
                'message' => 'No items require payment.',
            ];
        }

        $totalAmountPaid = 0;
        $paidItems = [];
        $partialItems = [];
        $unpaidItems = [];
        $currentBalance = $availableBalance;

        foreach ($payableItems as $item) {
            $remainingAmount = $item->total_price - $item->paid_amount;

            if ($currentBalance <= 0) {
                $unpaidItems[] = [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'total_price' => (float) $item->total_price,
                    'paid_amount' => (float) $item->paid_amount,
                    'remaining_amount' => $remainingAmount,
                ];
                continue;
            }

            if ($currentBalance >= $remainingAmount) {
                $amountToPay = $remainingAmount;
                $currentBalance -= $amountToPay;
                $totalAmountPaid += $amountToPay;

                $paidItems[] = [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'total_price' => (float) $item->total_price,
                    'paid_amount' => (float) $item->paid_amount + $amountToPay,
                    'remaining_amount' => 0,
                    'amount_to_pay' => $amountToPay,
                ];
            } else {
                $amountToPay = $currentBalance;
                $totalAmountPaid += $amountToPay;
                $currentBalance = 0;

                $partialItems[] = [
                    'id' => $item->id,
                    'item_type' => $item->item_type,
                    'description' => $item->description,
                    'total_price' => (float) $item->total_price,
                    'paid_amount' => (float) $item->paid_amount + $amountToPay,
                    'remaining_amount' => $remainingAmount - $amountToPay,
                    'amount_to_pay' => $amountToPay,
                ];
            }
        }

        $message = $this->generatePaymentMessage($totalAmountPaid, $paidItems, $partialItems, $unpaidItems);

        return [
            'success' => true,
            'amount_paid' => $totalAmountPaid,
            'remaining_balance' => $currentBalance,
            'paid_items' => $paidItems,
            'partial_items' => $partialItems,
            'unpaid_items' => $unpaidItems,
            'message' => $message,
        ];
    }

    /**
     * Generate a human-readable payment message.
     *
     * @param float $totalPaid
     * @param array $paidItems
     * @param array $partialItems
     * @param array $unpaidItems
     * @return string
     */
    private function generatePaymentMessage(float $totalPaid, array $paidItems, array $partialItems, array $unpaidItems): string
    {
        if ($totalPaid == 0) {
            return 'No payment was made due to insufficient balance.';
        }

        $parts = [];
        $parts[] = 'Payment of ' . number_format($totalPaid, 0, '.', ',') . ' VND successfully processed.';

        if (count($paidItems) > 0) {
            $parts[] = count($paidItems) . ' item(s) fully paid.';
        }

        if (count($partialItems) > 0) {
            $parts[] = count($partialItems) . ' item(s) partially paid.';
        }

        if (count($unpaidItems) > 0) {
            $parts[] = count($unpaidItems) . ' item(s) remain unpaid due to insufficient balance.';
        }

        return implode(' ', $parts);
    }
}
