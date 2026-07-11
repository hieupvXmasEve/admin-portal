<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Dng\Models\DngPaymentRequestReservationTarget;
use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\CreditApplication;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceCreditEntitlement;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinancePricingCatalog;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Credit intake tracer (wave 4 / ADR-0030).
 *
 * Creates a FinanceCreditEntitlement and applies it via the credit-application
 * ledger. Never materializes a negative FinanceCharge.
 */
class RequestFinanceCreditAction
{
    public function __construct(
        private readonly FinancePricingCatalog $pricingCatalog,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementService $settlementService,
        private readonly ReconcileChargeInstallmentsAction $reconcileChargeInstallments,
        private readonly SettlementMutationGuard $settlementMutationGuard,
    ) {}

    public function handle(FinanceIntakeData $intake): FinanceIntakeResult
    {
        return DB::transaction(function () use ($intake): FinanceIntakeResult {
            $this->assertCreditIntake($intake);
            $this->assertFactsCarryNoPayerIdentity($intake);
            $billingAccount = $this->resolveBillingAccount($intake);

            return $this->settlementMutationGuard->handle((int) $billingAccount->id, function () use ($intake, $billingAccount): FinanceIntakeResult {
                $entitlement = $this->findExistingEntitlement($intake);

                if (! $entitlement instanceof FinanceCreditEntitlement) {
                    $entitlement = $this->createApprovedEntitlement($intake, $billingAccount);
                }

                $applicationIds = $this->ensureApplications($entitlement, $intake);

                return new FinanceIntakeResult(
                    lifecycle_status: $entitlement->lifecycle_status,
                    amount: (float) $entitlement->amount,
                    currency: $entitlement->currency,
                    pricing_rule_version: $entitlement->pricing_rule_version,
                    finance_credit_entitlement_id: $entitlement->id,
                    credit_application_ids: $applicationIds,
                );
            });
        });
    }

    private function assertCreditIntake(FinanceIntakeData $intake): void
    {
        if ($intake->financial_effect !== FinancialEffect::Credit) {
            throw InvalidFinanceIntakePayload::missingFact('financial_effect=credit');
        }

        $definition = ObligationTypeRegistry::get($intake->obligation_type);

        if ($definition->financialEffect !== FinancialEffect::Credit) {
            throw new RuntimeException(
                "Obligation type [{$intake->obligation_type}] is not a credit financial effect."
            );
        }

        if (array_key_exists('pricing_rule_version', $intake->facts)) {
            throw InvalidFinanceIntakePayload::forbiddenPricingFact('pricing_rule_version');
        }
    }

    private function assertFactsCarryNoPayerIdentity(FinanceIntakeData $intake): void
    {
        if (array_key_exists('billing_account_id', $intake->facts)) {
            throw InvalidFinanceIntakePayload::forbiddenPayerFact('billing_account_id');
        }
    }

    private function findExistingEntitlement(FinanceIntakeData $intake): ?FinanceCreditEntitlement
    {
        return FinanceCreditEntitlement::query()
            ->where('source_system', $intake->source_system)
            ->where('source_kind', $intake->source_kind)
            ->where('source_ref', $intake->source_ref)
            ->where('entitlement_type', $intake->obligation_type)
            ->lockForUpdate()
            ->first();
    }

    private function createApprovedEntitlement(FinanceIntakeData $intake, BillingAccount $billingAccount): FinanceCreditEntitlement
    {
        $priced = $this->pricingCatalog->price($intake);
        $amount = abs((float) $priced['amount']);

        if ($amount <= 0) {
            throw InvalidFinanceIntakePayload::missingFact('amount');
        }

        return FinanceCreditEntitlement::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => $intake->source_system,
            'source_kind' => $intake->source_kind,
            'source_ref' => $intake->source_ref,
            'entitlement_type' => $intake->obligation_type,
            'lifecycle_status' => FinanceCreditEntitlement::STATUS_APPROVED,
            'allocation_status' => FinanceCreditEntitlement::ALLOCATION_AVAILABLE,
            'amount' => $amount,
            'currency' => $priced['currency'],
            'pricing_rule_version' => $priced['rule_version'],
            'pricing_snapshot' => $priced['snapshot'],
            'approved_at' => now(),
        ]);
    }

    /**
     * @return list<int>
     */
    private function ensureApplications(
        FinanceCreditEntitlement $entitlement,
        FinanceIntakeData $intake,
    ): array {
        $existingIds = CreditApplication::query()
            ->where('finance_credit_entitlement_id', $entitlement->id)
            ->where('entry_type', CreditApplication::ENTRY_APPLICATION)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($existingIds !== []) {
            return $existingIds;
        }

        $targetLines = $this->resolveTargetLines($entitlement, $intake);
        $remaining = (float) $entitlement->amount;
        $applicationIds = [];

        foreach ($targetLines as $line) {
            if ($remaining <= 0) {
                break;
            }

            if ($this->isHeld($line)) {
                continue;
            }

            $capacity = $this->settlementService->getLineOutstandingAmount($line);
            if ($capacity <= 0) {
                continue;
            }

            $applyAmount = min($remaining, $capacity);
            $application = CreditApplication::query()->create([
                'finance_credit_entitlement_id' => $entitlement->id,
                'invoice_line_id' => $line->id,
                'amount' => $applyAmount,
                'entry_type' => CreditApplication::ENTRY_APPLICATION,
                'source_ref_type' => $intake->source_kind,
                'source_ref_id' => null,
                'applied_at' => now(),
                'created_by' => auth()->id(),
            ]);

            $applicationIds[] = (int) $application->id;
            $remaining -= $applyAmount;

            $invoice = $line->invoice()->first();
            if ($invoice !== null) {
                $this->settlementService->recalculateInvoiceSnapshot($invoice);
            }
            if ($line->charge instanceof FinanceCharge) {
                $this->reconcileChargeInstallments->handle($line->charge);
            }
        }

        $this->refreshAllocationStatus($entitlement);

        return $applicationIds;
    }

    /**
     * @return Collection<int, InvoiceLine>
     */
    private function resolveTargetLines(
        FinanceCreditEntitlement $entitlement,
        FinanceIntakeData $intake,
    ): Collection {
        if (isset($intake->facts['invoice_line_id']) && is_numeric($intake->facts['invoice_line_id'])) {
            $line = InvoiceLine::query()
                ->with(['invoice', 'charge'])
                ->whereKey((int) $intake->facts['invoice_line_id'])
                ->where('status', 'active')
                ->where('amount_snapshot', '>', 0)
                ->first();

            return $line instanceof InvoiceLine ? collect([$line]) : collect();
        }

        $studentId = $this->resolveStudentId($entitlement, $intake);
        $semesterId = isset($intake->facts['semester_id']) && is_numeric($intake->facts['semester_id'])
            ? (int) $intake->facts['semester_id']
            : null;
        $invoiceId = isset($intake->facts['invoice_id']) && is_numeric($intake->facts['invoice_id'])
            ? (int) $intake->facts['invoice_id']
            : null;

        $query = InvoiceLine::query()
            ->with(['invoice', 'charge'])
            ->where('status', 'active')
            ->where('amount_snapshot', '>', 0)
            ->whereHas('charge', function ($chargeQuery): void {
                $chargeQuery->where('status', FinanceCharge::STATUS_ACTIVE)
                    ->where('amount', '>', 0);
            })
            ->whereHas('invoice', function ($invoiceQuery) use ($studentId, $semesterId, $invoiceId): void {
                $invoiceQuery->where('student_id', $studentId)
                    ->whereNotIn('status', ['cancelled', 'void']);

                if ($invoiceId !== null) {
                    $invoiceQuery->whereKey($invoiceId);
                }

                if ($semesterId !== null && $invoiceId === null) {
                    $invoiceQuery->where('semester_id', $semesterId);
                }
            })
            ->orderBy('created_at')
            ->orderBy('id');

        return $query->get();
    }

    private function refreshAllocationStatus(FinanceCreditEntitlement $entitlement): void
    {
        $applied = max(0.0, (float) CreditApplication::query()
            ->where('finance_credit_entitlement_id', $entitlement->id)
            ->sum('amount'));

        $amount = (float) $entitlement->amount;
        $status = FinanceCreditEntitlement::ALLOCATION_AVAILABLE;

        if ($applied >= $amount && $amount > 0) {
            $status = FinanceCreditEntitlement::ALLOCATION_FULLY_APPLIED;
        } elseif ($applied > 0) {
            $status = FinanceCreditEntitlement::ALLOCATION_PARTIALLY_APPLIED;
        }

        $entitlement->forceFill(['allocation_status' => $status])->save();
    }

    private function resolveBillingAccount(FinanceIntakeData $intake): BillingAccount
    {
        $studentId = $this->requiredIntFact($intake, 'student_id');

        return $this->billingAccountProvisioner->forStudent($studentId);
    }

    private function resolveStudentId(
        FinanceCreditEntitlement $entitlement,
        FinanceIntakeData $intake,
    ): int {
        $entitlement->loadMissing('billingAccount');

        if ($entitlement->billingAccount?->student_id !== null) {
            return (int) $entitlement->billingAccount->student_id;
        }

        return $this->requiredIntFact($intake, 'student_id');
    }

    private function requiredIntFact(FinanceIntakeData $intake, string $key): int
    {
        if (! array_key_exists($key, $intake->facts)) {
            throw InvalidFinanceIntakePayload::missingFact($key);
        }

        return (int) $intake->facts[$key];
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
