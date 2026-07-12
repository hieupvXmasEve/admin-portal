<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\FinanceChargeInstallment;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\Finance\SettlementPositionReader;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Reverses one credit ledger application without inventing a replacement plan. */
final class ReverseCreditApplicationAction
{
    public function __construct(
        private readonly SettlementMutationGuard $settlementMutationGuard,
        private readonly ReconcileChargeInstallmentsAction $reconcileChargeInstallments,
        private readonly SettlementPositionReader $settlementPositionReader,
        private readonly SettlementService $settlementService,
    ) {}

    /** @param list<array{installment_no:int,amount:float|string,due_date:string}>|null $confirmedPlan */
    public function handle(int $creditApplicationId, ?array $confirmedPlan = null): CreditApplication
    {
        return DB::transaction(function () use ($creditApplicationId, $confirmedPlan): CreditApplication {
            $application = CreditApplication::query()->lockForUpdate()->findOrFail($creditApplicationId);
            $entitlement = $application->financeCreditEntitlement()->lockForUpdate()->firstOrFail();
            $billingAccountId = (int) $entitlement->billing_account_id;
            BillingAccount::query()->lockForUpdate()->findOrFail($billingAccountId);
            $line = $application->invoiceLine()->lockForUpdate()->firstOrFail();
            $charge = $line->charge()->lockForUpdate()->firstOrFail();

            if ($this->isHeld($line)) {
                throw ValidationException::withMessages(['credit_application_id' => 'Cần kiểm tra: target đang được DNG giữ, không thể reverse credit hoặc rewrite collection plan.']);
            }

            $requiresConfirmedPlan = FinanceChargeInstallment::query()
                ->where('finance_charge_id', $charge->id)
                ->where('status', FinanceChargeInstallment::STATUS_PENDING)
                ->doesntExist();
            if ($requiresConfirmedPlan && $confirmedPlan === null) {
                throw ValidationException::withMessages(['collection_plan' => 'Cần xác nhận số tiền và ngày đến hạn của collection plan mới trước khi reverse credit.']);
            }

            return $this->settlementMutationGuard->handle($billingAccountId, function () use ($application, $line, $charge, $confirmedPlan, $requiresConfirmedPlan): CreditApplication {
                if (CreditApplication::query()
                    ->where('source_ref_type', self::class)
                    ->where('source_ref_id', $application->id)
                    ->exists()) {
                    throw ValidationException::withMessages(['credit_application_id' => 'Credit application has already been reversed.']);
                }
                $reversal = CreditApplication::query()->create([
                    'finance_credit_entitlement_id' => $application->finance_credit_entitlement_id,
                    'invoice_line_id' => $line->id,
                    'amount' => -abs((float) $application->amount),
                    'entry_type' => CreditApplication::ENTRY_REVERSAL,
                    'source_ref_type' => self::class,
                    'source_ref_id' => $application->id,
                    'applied_at' => now(),
                    'created_by' => auth()->id(),
                ]);
                if ($requiresConfirmedPlan) {
                    $this->createConfirmedPendingPlan($charge->id, $confirmedPlan ?? []);
                }
                $this->reconcileChargeInstallments->handle($charge);
                $this->settlementService->recalculateInvoiceSnapshot($line->invoice()->firstOrFail());

                return $reversal;
            });
        });
    }

    /** @param list<array{installment_no:int,amount:float|string,due_date:string}> $confirmedPlan */
    private function createConfirmedPendingPlan(int $chargeId, array $confirmedPlan): void
    {
        if ($confirmedPlan === []) {
            throw ValidationException::withMessages(['collection_plan' => 'Cần xác nhận ít nhất một installment với số tiền và ngày đến hạn.']);
        }

        $lineIds = InvoiceLine::query()->where('charge_id', $chargeId)->where('status', 'active')->pluck('id')->all();
        $position = $this->settlementPositionReader->forPayableLines($lineIds);
        if (! $position->isValid() || $position->amounts === null) {
            throw ValidationException::withMessages(['collection_plan' => 'Cần kiểm tra Settlement Position trước khi xác nhận collection plan.']);
        }

        $expectedCents = (int) round(((float) $position->amounts->remaining->amount) * 100);
        $totalCents = 0;
        $previousDueDate = null;
        foreach (array_values($confirmedPlan) as $index => $installment) {
            $amount = (float) ($installment['amount'] ?? 0);
            $dueDate = (string) ($installment['due_date'] ?? '');
            if (($installment['installment_no'] ?? null) !== $index + 1 || $amount <= 0 || $dueDate === '' || ($previousDueDate !== null && $dueDate < $previousDueDate)) {
                throw ValidationException::withMessages(['collection_plan' => 'Collection plan phải có số installment liên tiếp, số tiền dương và due date tăng dần.']);
            }
            $totalCents += (int) round($amount * 100);
            $previousDueDate = $dueDate;
        }
        if ($totalCents !== $expectedCents) {
            throw ValidationException::withMessages(['collection_plan' => 'Tổng collection plan phải bằng canonical collectible sau reversal.']);
        }

        foreach ($confirmedPlan as $installment) {
            FinanceChargeInstallment::query()->create([
                'finance_charge_id' => $chargeId,
                'installment_no' => $installment['installment_no'],
                'amount' => number_format((float) $installment['amount'], 2, '.', ''),
                'due_date' => $installment['due_date'],
                'status' => FinanceChargeInstallment::STATUS_PENDING,
            ]);
        }
    }

    private function isHeld(InvoiceLine $line): bool
    {
        return DngPaymentRequestReservationTarget::query()
            ->where('invoice_line_id', $line->id)
            ->whereHas('dngPaymentRequest', fn ($query) => $query->holdingCollection())
            ->lockForUpdate()
            ->exists();
    }
}
