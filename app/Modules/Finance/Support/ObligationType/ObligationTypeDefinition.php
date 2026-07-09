<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\ObligationType;

use App\Modules\Finance\Enums\CancellationPolicy;
use App\Modules\Finance\Enums\PricingStrategy;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;

/**
 * One declarative entry in the code-owned Obligation Type Registry (ADR-0027).
 */
final readonly class ObligationTypeDefinition
{
    /**
     * @param  list<string>  $allowedSourceKinds  Neutral source_kind values accepted at intake.
     * @param  string|null  $dngCollectionCode  DNG fee_type used by auto-map; null → never auto-mapped (falls to KHAC for debits).
     * @param  string|null  $feeMonitorSourceKey  Fee Monitor expected-source id; null → not tracked as expected fee.
     * @param  string|null  $permission  Primary staff permission key for generating/managing this type, if any.
     */
    public function __construct(
        public string $type,
        public FinancialEffect $financialEffect,
        public array $allowedSourceKinds,
        public PricingStrategy $pricingStrategy,
        public ?string $dngCollectionCode,
        public bool $supportsInstallments,
        public CancellationPolicy $cancellationPolicy,
        public ?string $permission,
        public string $label,
        public ?string $feeMonitorSourceKey = null,
        public bool $feeMonitorMandatory = false,
        public bool $feeMonitorMissingInference = false,
        public bool $feeMonitorMissingInferenceUsesAcadRetGate = false,
    ) {}

    public function isDebit(): bool
    {
        return $this->financialEffect === FinancialEffect::Debit;
    }

    public function isCredit(): bool
    {
        return $this->financialEffect === FinancialEffect::Credit;
    }

    public function isDiscount(): bool
    {
        return $this->financialEffect === FinancialEffect::Discount;
    }

    public function isTrackedByFeeMonitor(): bool
    {
        return $this->feeMonitorSourceKey !== null;
    }

    /**
     * True when FinancePricingCatalog must supply amount/currency at intake.
     */
    public function requiresPricingCatalog(): bool
    {
        return $this->pricingStrategy->requiresPricingCatalog();
    }

    /**
     * Effective Fee Monitor missing-inference switch (may honour ACAD-RET gate).
     */
    public function resolveFeeMonitorMissingInference(bool $acadRetGateEnabled): bool
    {
        if (! $this->feeMonitorMissingInference) {
            return false;
        }

        if ($this->feeMonitorMissingInferenceUsesAcadRetGate) {
            return $acadRetGateEnabled;
        }

        return true;
    }
}
