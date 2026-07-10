<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\ObligationType;

use App\Modules\Finance\Enums\CancellationPolicy;
use App\Modules\Finance\Enums\PricingStrategy;
use App\Modules\Finance\Models\FinanceCharge;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;
use InvalidArgumentException;

/**
 * Code-owned Obligation Type Registry (ADR-0027).
 *
 * Single source of per-type behaviour facts: financial effect, allowed source
 * kinds, pricing strategy, DNG collection code, Fee Monitor missing-inference,
 * installment support, cancellation policy, and permission.
 *
 * Pricing *rules* (amounts, effective dates) live in finance_pricing_catalog_items;
 * this registry never stores runtime prices.
 */
final class ObligationTypeRegistry
{
    /**
     * Planned v2 entitlement type that has no finance_charges.charge_type row.
     * Wave 5 materializes egc_retake as FinanceDiscountEntitlement only.
     */
    public const TYPE_EGC_RETAKE = 'egc_retake';

    /**
     * @return array<string, ObligationTypeDefinition>
     */
    public static function all(): array
    {
        static $definitions = null;

        if ($definitions === null) {
            $definitions = self::buildDefinitions();
        }

        return $definitions;
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    public static function get(string $type): ObligationTypeDefinition
    {
        $definition = self::all()[$type] ?? null;

        if (! $definition instanceof ObligationTypeDefinition) {
            throw new InvalidArgumentException("Unknown obligation type [{$type}].");
        }

        return $definition;
    }

    /**
     * @return list<string>
     */
    public static function types(): array
    {
        return array_keys(self::all());
    }

    /**
     * Types that may appear as finance_charges.charge_type (debit + legacy credit/discount rows).
     *
     * @return list<string>
     */
    public static function chargeTypes(): array
    {
        return array_values(array_filter(
            self::types(),
            static fn (string $type): bool => $type !== self::TYPE_EGC_RETAKE,
        ));
    }

    /**
     * @return list<ObligationTypeDefinition>
     */
    public static function byFinancialEffect(FinancialEffect $effect): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (ObligationTypeDefinition $definition): bool => $definition->financialEffect === $effect,
        ));
    }

    /**
     * DNG fee_type for a charge_type. Matches historical DngFeeTypeOptions::fromChargeType:
     * unknown / unmapped types → KHAC. Credits are never pushed; callers must filter amount > 0.
     */
    public static function dngCollectionCodeFor(string $chargeType): string
    {
        if (! self::has($chargeType)) {
            return 'KHAC';
        }

        $code = self::get($chargeType)->dngCollectionCode;

        return $code ?? 'KHAC';
    }

    /**
     * Reverse of dngCollectionCodeFor for debit charge types that auto-map to a code.
     * Manual-only DNG codes (PRE, THHB, F1, GC) have no reverse charge-type set.
     *
     * @return list<string>
     */
    public static function chargeTypesForDngCollectionCode(string $dngFeeType): array
    {
        $types = [];

        foreach (self::all() as $definition) {
            if ($definition->dngCollectionCode === $dngFeeType && $definition->isDebit()) {
                $types[] = $definition->type;
            }
        }

        return $types;
    }

    /**
     * Definitions tracked by Fee Monitor expected-fee catalog.
     *
     * @return list<ObligationTypeDefinition>
     */
    public static function feeMonitorTracked(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (ObligationTypeDefinition $definition): bool => $definition->isTrackedByFeeMonitor(),
        ));
    }

    /**
     * Obligation types whose intake path requires an active pricing catalog rule.
     *
     * @return list<ObligationTypeDefinition>
     */
    public static function requiringPricingCatalog(): array
    {
        return array_values(array_filter(
            self::all(),
            static fn (ObligationTypeDefinition $definition): bool => $definition->requiresPricingCatalog(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function typesRequiringPricingCatalog(): array
    {
        return array_map(
            static fn (ObligationTypeDefinition $definition): string => $definition->type,
            self::requiringPricingCatalog(),
        );
    }

    /**
     * @return array<string, ObligationTypeDefinition>
     */
    private static function buildDefinitions(): array
    {
        $entries = [
            // ── Debits (current finance_charges.charge_type + inventory) ──
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_RETAKE_FEE,
                financialEffect: FinancialEffect::Debit,
                // legacy_course_registration: FixBillingException repair for pre-CourseRetakeRegistration rows.
                allowedSourceKinds: ['course_retake_registration', 'legacy_course_registration'],
                pricingStrategy: PricingStrategy::CatalogFixed,
                dngCollectionCode: 'HL',
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::SourceCancellationContract,
                permission: 'create_retake_course_charge',
                label: 'Phí học lại',
                feeMonitorSourceKey: 'course_retake',
                feeMonitorMandatory: true,
                feeMonitorMissingInference: true,
                feeMonitorMissingInferenceUsesAcadRetGate: true,
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_EXAM_RESIT_FEE,
                financialEffect: FinancialEffect::Debit,
                allowedSourceKinds: ['exam_resit_attempt'],
                pricingStrategy: PricingStrategy::CatalogFixed,
                dngCollectionCode: 'PTL',
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::SourceCancellationContract,
                permission: 'create_retake_course_charge',
                label: 'Phí thi lại',
                feeMonitorSourceKey: 'exam_resit',
                feeMonitorMandatory: true,
                feeMonitorMissingInference: true,
                feeMonitorMissingInferenceUsesAcadRetGate: true,
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_TUITION_TERM,
                financialEffect: FinancialEffect::Debit,
                allowedSourceKinds: ['major_batch', 'batch_studio', 'legacy_tuition'],
                pricingStrategy: PricingStrategy::GeneratorAmount,
                dngCollectionCode: 'HP',
                supportsInstallments: true,
                cancellationPolicy: CancellationPolicy::StaffVoid,
                permission: 'create_finance_charges',
                label: 'Học phí theo kế hoạch',
                feeMonitorSourceKey: 'tuition_plan',
                feeMonitorMandatory: true,
                feeMonitorMissingInference: true,
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_EGC_LEVEL_FEE,
                financialEffect: FinancialEffect::Debit,
                allowedSourceKinds: ['egc_batch', 'batch_studio', 'legacy_egc'],
                pricingStrategy: PricingStrategy::GeneratorAmount,
                dngCollectionCode: 'HP',
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::StaffVoid,
                permission: 'generate_egc_finance_charges',
                label: 'Phí EGC',
                feeMonitorSourceKey: 'egc_tuition',
                feeMonitorMandatory: true,
                feeMonitorMissingInference: true,
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_COURSE_FEE,
                financialEffect: FinancialEffect::Debit,
                allowedSourceKinds: ['legacy_course_fee'],
                pricingStrategy: PricingStrategy::GeneratorAmount,
                dngCollectionCode: 'HP',
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::AuditPending,
                permission: null,
                label: 'Phí môn học',
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_BHYT,
                financialEffect: FinancialEffect::Debit,
                allowedSourceKinds: ['non_academic_batch', 'csv_import'],
                pricingStrategy: PricingStrategy::GeneratorAmount,
                dngCollectionCode: 'BHYT',
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::StaffVoid,
                permission: 'create_finance_charges',
                label: 'BHYT',
                feeMonitorSourceKey: 'bhyt_health_insurance',
                feeMonitorMandatory: false,
                feeMonitorMissingInference: false,
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_MANUAL_FEE,
                financialEffect: FinancialEffect::Debit,
                allowedSourceKinds: ['manual_fee'],
                pricingStrategy: PricingStrategy::StaffSupplied,
                dngCollectionCode: 'KHAC',
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::StaffVoid,
                permission: 'create_finance_charges',
                label: 'Phí thủ công',
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_ADMISSION_FEE,
                financialEffect: FinancialEffect::Debit,
                allowedSourceKinds: ['admission_enrollment', 'manual_fee'],
                pricingStrategy: PricingStrategy::StaffSupplied,
                // fromChargeType historically does not map admission_fee (falls to KHAC).
                // PRE remains a manual-only DNG dropdown code (see PRD inventory).
                dngCollectionCode: null,
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::StaffVoid,
                permission: 'create_finance_charges',
                label: 'Lệ phí tuyển sinh / nhập học',
                feeMonitorSourceKey: 'admission_enrollment',
                feeMonitorMandatory: false,
                feeMonitorMissingInference: false,
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_ADJUSTMENT,
                financialEffect: FinancialEffect::Debit,
                // defer_forfeit: FORFEIT settlement mints a positive debit via intake (wave 7).
                allowedSourceKinds: ['manual_adjustment', 'settlement_correction', 'defer_forfeit'],
                pricingStrategy: PricingStrategy::StaffSupplied,
                dngCollectionCode: 'KHAC',
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::AuditPending,
                permission: 'create_finance_charges',
                label: 'Điều chỉnh',
            ),

            // ── Legacy credit charge_types (wave 4 → FinanceCreditEntitlement) ──
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_DEFER_CREDIT,
                financialEffect: FinancialEffect::Credit,
                allowedSourceKinds: ['defer_settlement'],
                pricingStrategy: PricingStrategy::PolicyComputed,
                dngCollectionCode: null,
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::PolicySettlement,
                permission: null,
                label: 'Tín dụng bảo lưu',
            ),
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_EGC_EXEMPT_CREDIT,
                financialEffect: FinancialEffect::Credit,
                allowedSourceKinds: ['egc_exemption', 'egc_major_entry_credit', 'legacy_egc_exempt_credit'],
                pricingStrategy: PricingStrategy::PolicyComputed,
                dngCollectionCode: null,
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::EntitlementRevoke,
                permission: 'generate_egc_finance_charges',
                label: 'Tín dụng miễn EGC',
            ),
            // scholarship_credit is dual-nature (grant credit vs fee-specific discount);
            // wave 4 classifier splits carriers. Registry primary effect is Credit (grant-like default).
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_SCHOLARSHIP_CREDIT,
                financialEffect: FinancialEffect::Credit,
                allowedSourceKinds: ['scholarship_application', 'scholarship_award'],
                pricingStrategy: PricingStrategy::PolicyComputed,
                dngCollectionCode: null,
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::EntitlementRevoke,
                permission: null,
                label: 'Tín dụng / chiết khấu học bổng',
            ),

            // ── Legacy discount-as-negative-charge (wave 4 → FinanceDiscountEntitlement) ──
            new ObligationTypeDefinition(
                type: FinanceCharge::TYPE_VOUCHER_CREDIT,
                financialEffect: FinancialEffect::Discount,
                allowedSourceKinds: ['voucher_application'],
                pricingStrategy: PricingStrategy::PolicyComputed,
                dngCollectionCode: null,
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::EntitlementRevoke,
                permission: null,
                label: 'Chiết khấu voucher',
            ),

            // ── Planned v2-only entitlement (no charge_type enum value) ──
            new ObligationTypeDefinition(
                type: self::TYPE_EGC_RETAKE,
                financialEffect: FinancialEffect::Discount,
                allowedSourceKinds: ['egc_retake_adjustment', 'legacy_egc_retake'],
                pricingStrategy: PricingStrategy::PolicyComputed,
                dngCollectionCode: null,
                supportsInstallments: false,
                cancellationPolicy: CancellationPolicy::EntitlementRevoke,
                permission: 'apply_egc_retake_adjustment',
                label: 'Chiết khấu học lại EGC',
            ),
        ];

        $definitions = [];
        foreach ($entries as $entry) {
            $definitions[$entry->type] = $entry;
        }

        return $definitions;
    }
}
