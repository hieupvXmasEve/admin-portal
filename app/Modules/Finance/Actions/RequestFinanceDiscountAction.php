<?php

declare(strict_types=1);

namespace App\Modules\Finance\Actions;

use App\Modules\Finance\Models\BillingAccount;
use App\Modules\Finance\Models\DiscountAllocation;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Models\FinanceDiscountEntitlement;
use App\Modules\Finance\Models\InvoiceDiscount;
use App\Modules\Finance\Models\InvoiceLine;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\BillingAccountProvisioner;
use App\Modules\Finance\Support\FinancePricingCatalog;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Modules\Finance\Support\SettlementMutationGuard;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeData;
use App\Shared\Contracts\Finance\DTO\FinanceIntakeResult;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use App\Shared\Contracts\Finance\Exceptions\InvalidFinanceIntakePayload;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Discount intake tracer (wave 4 / ADR-0030).
 *
 * Creates a FinanceDiscountEntitlement and allocates through the existing
 * invoice_discounts / discount_allocations carrier. Never materializes a
 * negative FinanceCharge and never writes credit applications.
 */
class RequestFinanceDiscountAction
{
    public function __construct(
        private readonly FinancePricingCatalog $pricingCatalog,
        private readonly BillingAccountProvisioner $billingAccountProvisioner,
        private readonly SettlementService $settlementService,
        private readonly SettlementMutationGuard $settlementMutationGuard,
    ) {}

    public function handle(FinanceIntakeData $intake): FinanceIntakeResult
    {
        return DB::transaction(function () use ($intake): FinanceIntakeResult {
            $this->assertDiscountIntake($intake);
            $this->assertFactsCarryNoPayerIdentity($intake);
            $billingAccount = $this->resolveBillingAccount($intake);

            return $this->settlementMutationGuard->handle((int) $billingAccount->id, function () use ($intake): FinanceIntakeResult {
                $entitlement = $this->findExistingEntitlement($intake);

                if (! $entitlement instanceof FinanceDiscountEntitlement) {
                    $entitlement = $this->createApprovedEntitlement($intake);
                }

                $discountIds = $this->ensureDiscountAllocations($entitlement, $intake);

                return new FinanceIntakeResult(
                    lifecycle_status: $entitlement->lifecycle_status,
                    amount: (float) $entitlement->amount,
                    currency: $entitlement->currency,
                    pricing_rule_version: $entitlement->pricing_rule_version,
                    finance_discount_entitlement_id: $entitlement->id,
                    invoice_discount_ids: $discountIds,
                );
            });
        });
    }

    private function assertDiscountIntake(FinanceIntakeData $intake): void
    {
        if ($intake->financial_effect !== FinancialEffect::Discount) {
            throw InvalidFinanceIntakePayload::missingFact('financial_effect=discount');
        }

        $definition = ObligationTypeRegistry::get($intake->obligation_type);

        if ($definition->financialEffect !== FinancialEffect::Discount) {
            throw new RuntimeException(
                "Obligation type [{$intake->obligation_type}] is not a discount financial effect."
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

    private function findExistingEntitlement(FinanceIntakeData $intake): ?FinanceDiscountEntitlement
    {
        return FinanceDiscountEntitlement::query()
            ->where('source_system', $intake->source_system)
            ->where('source_kind', $intake->source_kind)
            ->where('source_ref', $intake->source_ref)
            ->where('entitlement_type', $intake->obligation_type)
            ->lockForUpdate()
            ->first();
    }

    private function createApprovedEntitlement(FinanceIntakeData $intake): FinanceDiscountEntitlement
    {
        $priced = $this->pricingCatalog->price($intake);
        $billingAccount = $this->resolveBillingAccount($intake);
        $amount = abs((float) $priced['amount']);

        if ($amount <= 0) {
            throw InvalidFinanceIntakePayload::missingFact('amount');
        }

        return FinanceDiscountEntitlement::query()->create([
            'billing_account_id' => $billingAccount->id,
            'source_system' => $intake->source_system,
            'source_kind' => $intake->source_kind,
            'source_ref' => $intake->source_ref,
            'entitlement_type' => $intake->obligation_type,
            'lifecycle_status' => FinanceDiscountEntitlement::STATUS_APPROVED,
            'allocation_status' => FinanceDiscountEntitlement::ALLOCATION_AVAILABLE,
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
    private function ensureDiscountAllocations(
        FinanceDiscountEntitlement $entitlement,
        FinanceIntakeData $intake,
    ): array {
        $existingIds = InvoiceDiscount::query()
            ->where('finance_discount_entitlement_id', $entitlement->id)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($existingIds !== []) {
            return $existingIds;
        }

        $invoice = $this->resolveTargetInvoice($entitlement, $intake);

        if (! $invoice instanceof StudentInvoice) {
            throw InvalidFinanceIntakePayload::missingFact('invoice_id');
        }

        $discountType = $this->discountTypeForEntitlement($entitlement->entitlement_type);
        $referenceId = isset($intake->facts['voucher_application_id']) && is_numeric($intake->facts['voucher_application_id'])
            ? (int) $intake->facts['voucher_application_id']
            : (isset($intake->facts['reference_id']) && is_numeric($intake->facts['reference_id'])
                ? (int) $intake->facts['reference_id']
                : null);

        $description = isset($intake->facts['description']) && is_string($intake->facts['description'])
            ? $intake->facts['description']
            : $discountType;

        $discountSource = isset($intake->facts['discount_source']) && is_string($intake->facts['discount_source'])
            ? $intake->facts['discount_source']
            : $intake->source_kind;

        $targetLine = $this->resolveTargetLine($entitlement, $intake, $invoice);

        if ($targetLine instanceof InvoiceLine) {
            $discount = $this->createTargetedLineDiscount(
                invoice: $invoice,
                line: $targetLine,
                entitlement: $entitlement,
                discountType: $discountType,
                discountSource: $discountSource,
                description: $description,
                referenceId: $referenceId,
                allocationRule: $this->allocationRuleForEntitlement($entitlement->entitlement_type, $intake),
            );
        } else {
            $discount = $this->settlementService->createOrRefreshInvoiceDiscount(
                invoice: $invoice,
                discountType: $discountType,
                amount: (float) $entitlement->amount,
                discountSource: $discountSource,
                description: $description,
                referenceId: $referenceId,
                approvedBy: auth()->id(),
            );

            $discount->forceFill([
                'finance_discount_entitlement_id' => $entitlement->id,
            ])->save();
        }

        $this->refreshAllocationStatus($entitlement);

        return [(int) $discount->id];
    }

    private function resolveTargetLine(
        FinanceDiscountEntitlement $entitlement,
        FinanceIntakeData $intake,
        StudentInvoice $invoice,
    ): ?InvoiceLine {
        if (! isset($intake->facts['invoice_line_id']) || ! is_numeric($intake->facts['invoice_line_id'])) {
            return null;
        }

        $studentId = $this->resolveStudentId($entitlement, $intake);

        $line = InvoiceLine::query()
            ->with(['invoice', 'charge'])
            ->whereKey((int) $intake->facts['invoice_line_id'])
            ->where('invoice_id', $invoice->id)
            ->where('status', 'active')
            ->where('amount_snapshot', '>', 0)
            ->whereHas('invoice', function ($query) use ($studentId): void {
                $query->where('student_id', $studentId)
                    ->whereNotIn('status', ['cancelled', 'void']);
            })
            ->first();

        if (! $line instanceof InvoiceLine) {
            throw InvalidFinanceIntakePayload::invalidFact(
                'invoice_line_id',
                'must be an active line on the target invoice for the entitlement student'
            );
        }

        return $line;
    }

    private function createTargetedLineDiscount(
        StudentInvoice $invoice,
        InvoiceLine $line,
        FinanceDiscountEntitlement $entitlement,
        string $discountType,
        string $discountSource,
        string $description,
        ?int $referenceId,
        string $allocationRule,
    ): InvoiceDiscount {
        $billingAccountId = $this->billingAccountIdForInvoice($invoice);

        return $this->settlementMutationGuard->handle($billingAccountId, function () use ($invoice, $line, $entitlement, $discountType, $discountSource, $description, $referenceId, $allocationRule): InvoiceDiscount {
            if ((int) $line->invoice_id !== (int) $invoice->id) {
                throw InvalidFinanceIntakePayload::invalidFact(
                    'invoice_line_id',
                    'must belong to the target invoice'
                );
            }

            $amount = (float) $entitlement->amount;

            $lineCapacity = max(
                0.0,
                (float) $line->amount_snapshot - $this->settlementService->getLineDiscountAmount($line)
            );
            $allocateAmount = min($amount, $lineCapacity);

            if ($allocateAmount <= 0) {
                throw InvalidFinanceIntakePayload::invalidFact(
                    'invoice_line_id',
                    'has no remaining discount capacity'
                );
            }

            $discount = InvoiceDiscount::query()->create([
                'invoice_id' => $invoice->id,
                'discount_type' => $discountType,
                'discount_source' => $discountSource,
                'description' => $description,
                'amount' => $amount,
                'status' => 'active',
                'reference_id' => $referenceId,
                'approved_by' => auth()->id(),
                'finance_discount_entitlement_id' => $entitlement->id,
            ]);

            DiscountAllocation::query()->create([
                'invoice_discount_id' => $discount->id,
                'invoice_line_id' => $line->id,
                'amount' => $allocateAmount,
                'entry_type' => 'allocation',
                'allocation_rule' => $allocationRule,
            ]);

            // A newly approved discount can make an already-paid line
            // temporarily overpaid. Release that exact surplus before reading
            // the invoice snapshot again, which intentionally rejects an
            // overpaid Settlement Position.
            $this->settlementService->releaseLineOverpayment(
                $line,
                auth()->id(),
                InvoiceDiscount::class,
                $discount->id,
            );
            $this->settlementService->recalculateInvoiceSnapshot($invoice);

            return $discount;
        });
    }

    private function allocationRuleForEntitlement(string $entitlementType, FinanceIntakeData $intake): string
    {
        if (isset($intake->facts['allocation_rule']) && is_string($intake->facts['allocation_rule']) && $intake->facts['allocation_rule'] !== '') {
            return $intake->facts['allocation_rule'];
        }

        return match ($entitlementType) {
            ObligationTypeRegistry::TYPE_EGC_RETAKE => 'egc_retake_target',
            default => 'current_line_chronology',
        };
    }

    private function resolveTargetInvoice(
        FinanceDiscountEntitlement $entitlement,
        FinanceIntakeData $intake,
    ): ?StudentInvoice {
        if (isset($intake->facts['invoice_id']) && is_numeric($intake->facts['invoice_id'])) {
            return StudentInvoice::query()
                ->whereKey((int) $intake->facts['invoice_id'])
                ->whereNotIn('status', ['cancelled', 'void'])
                ->first();
        }

        if (isset($intake->facts['invoice_line_id']) && is_numeric($intake->facts['invoice_line_id'])) {
            $line = InvoiceLine::query()
                ->with('invoice')
                ->whereKey((int) $intake->facts['invoice_line_id'])
                ->where('status', 'active')
                ->first();

            return $line?->invoice;
        }

        $studentId = $this->resolveStudentId($entitlement, $intake);
        $semesterId = isset($intake->facts['semester_id']) && is_numeric($intake->facts['semester_id'])
            ? (int) $intake->facts['semester_id']
            : null;

        $query = StudentInvoice::query()
            ->where('student_id', $studentId)
            ->whereNotIn('status', ['cancelled', 'void'])
            ->orderByDesc('id');

        if ($semesterId !== null) {
            $query->where('semester_id', $semesterId);
        }

        return $query->first();
    }

    private function discountTypeForEntitlement(string $entitlementType): string
    {
        return match ($entitlementType) {
            FinanceCharge::TYPE_VOUCHER_CREDIT => 'voucher',
            ObligationTypeRegistry::TYPE_EGC_RETAKE => 'egc_retake',
            default => throw new RuntimeException(
                "Discount intake for entitlement type [{$entitlementType}] is not supported yet."
            ),
        };
    }

    private function refreshAllocationStatus(FinanceDiscountEntitlement $entitlement): void
    {
        $discountIds = InvoiceDiscount::query()
            ->where('finance_discount_entitlement_id', $entitlement->id)
            ->pluck('id');

        $allocated = $discountIds->isEmpty()
            ? 0.0
            : max(0.0, (float) DiscountAllocation::query()
                ->whereIn('invoice_discount_id', $discountIds)
                ->sum('amount'));

        $amount = (float) $entitlement->amount;
        $status = FinanceDiscountEntitlement::ALLOCATION_AVAILABLE;

        if ($allocated >= $amount && $amount > 0) {
            $status = FinanceDiscountEntitlement::ALLOCATION_FULLY_ALLOCATED;
        } elseif ($allocated > 0) {
            $status = FinanceDiscountEntitlement::ALLOCATION_PARTIALLY_ALLOCATED;
        }

        $entitlement->forceFill(['allocation_status' => $status])->save();
    }

    private function resolveBillingAccount(FinanceIntakeData $intake): BillingAccount
    {
        $studentId = $this->requiredIntFact($intake, 'student_id');

        return $this->billingAccountProvisioner->forStudent($studentId);
    }

    private function billingAccountIdForInvoice(StudentInvoice $invoice): int
    {
        return (int) $this->billingAccountProvisioner
            ->forStudent((int) $invoice->student_id)
            ->id;
    }

    private function resolveStudentId(
        FinanceDiscountEntitlement $entitlement,
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
}
