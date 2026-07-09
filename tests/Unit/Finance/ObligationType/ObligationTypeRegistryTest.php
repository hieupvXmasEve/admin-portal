<?php

declare(strict_types=1);

use App\Modules\Finance\Dng\Support\DngFeeTypeOptions;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\ObligationType\ObligationTypeRegistry;
use App\Modules\Finance\Support\Reporting\FeeMonitorExpectedFeeCatalog as Catalog;
use App\Shared\Contracts\Finance\Enums\FinancialEffect;

it('registers every FinanceCharge::CHARGE_TYPES value', function () {
    foreach (FinanceCharge::CHARGE_TYPES as $chargeType) {
        expect(ObligationTypeRegistry::has($chargeType))->toBeTrue(
            "Missing registry entry for charge_type [{$chargeType}]."
        );
    }
});

it('registers the planned egc_retake discount entitlement type', function () {
    $definition = ObligationTypeRegistry::get(ObligationTypeRegistry::TYPE_EGC_RETAKE);

    expect($definition->financialEffect)->toBe(FinancialEffect::Discount)
        ->and($definition->dngCollectionCode)->toBeNull()
        ->and($definition->isTrackedByFeeMonitor())->toBeFalse();
});

it('assigns financial effects consistent with CREDIT_CHARGE_TYPES and planned discounts', function () {
    foreach (FinanceCharge::CREDIT_CHARGE_TYPES as $creditType) {
        // voucher_credit is a discount entitlement target; others are credit.
        if ($creditType === FinanceCharge::TYPE_VOUCHER_CREDIT) {
            expect(ObligationTypeRegistry::get($creditType)->financialEffect)
                ->toBe(FinancialEffect::Discount);

            continue;
        }

        expect(ObligationTypeRegistry::get($creditType)->financialEffect)
            ->toBe(FinancialEffect::Credit);
    }

    expect(ObligationTypeRegistry::get(FinanceCharge::TYPE_TUITION_TERM)->financialEffect)
        ->toBe(FinancialEffect::Debit);
});

it('keeps DngFeeTypeOptions::fromChargeType identical to known auto-map codes', function () {
    $expected = [
        FinanceCharge::TYPE_TUITION_TERM => 'HP',
        FinanceCharge::TYPE_EGC_LEVEL_FEE => 'HP',
        FinanceCharge::TYPE_COURSE_FEE => 'HP',
        FinanceCharge::TYPE_RETAKE_FEE => 'HL',
        FinanceCharge::TYPE_EXAM_RESIT_FEE => 'PTL',
        FinanceCharge::TYPE_BHYT => 'BHYT',
        FinanceCharge::TYPE_MANUAL_FEE => 'KHAC',
        FinanceCharge::TYPE_ADJUSTMENT => 'KHAC',
        // admission_fee historically falls through to KHAC (PRE is manual-only).
        FinanceCharge::TYPE_ADMISSION_FEE => 'KHAC',
        // credits: callers filter amount > 0; code still returns KHAC fallback.
        FinanceCharge::TYPE_DEFER_CREDIT => 'KHAC',
        'unknown_future_type' => 'KHAC',
    ];

    foreach ($expected as $chargeType => $dngCode) {
        expect(DngFeeTypeOptions::fromChargeType($chargeType))->toBe($dngCode)
            ->and(ObligationTypeRegistry::dngCollectionCodeFor($chargeType))->toBe($dngCode);
    }
});

it('derives reverse DNG fee-type map with the same charge types as before', function () {
    expect(ObligationTypeRegistry::chargeTypesForDngCollectionCode('HP'))
        ->toEqualCanonicalizing([
            FinanceCharge::TYPE_TUITION_TERM,
            FinanceCharge::TYPE_EGC_LEVEL_FEE,
            FinanceCharge::TYPE_COURSE_FEE,
        ])
        ->and(ObligationTypeRegistry::chargeTypesForDngCollectionCode('HL'))
        ->toBe([FinanceCharge::TYPE_RETAKE_FEE])
        ->and(ObligationTypeRegistry::chargeTypesForDngCollectionCode('PTL'))
        ->toBe([FinanceCharge::TYPE_EXAM_RESIT_FEE])
        ->and(ObligationTypeRegistry::chargeTypesForDngCollectionCode('BHYT'))
        ->toBe([FinanceCharge::TYPE_BHYT])
        ->and(ObligationTypeRegistry::chargeTypesForDngCollectionCode('KHAC'))
        ->toEqualCanonicalizing([
            FinanceCharge::TYPE_MANUAL_FEE,
            FinanceCharge::TYPE_ADJUSTMENT,
        ]);
});

it('exposes Fee Monitor mandatory and missing-inference facts from the registry', function () {
    expect(Catalog::isMandatory(Catalog::SOURCE_TUITION_PLAN))->toBeTrue()
        ->and(Catalog::isMandatory(Catalog::SOURCE_EGC_TUITION))->toBeTrue()
        ->and(Catalog::isMandatory(Catalog::SOURCE_COURSE_RETAKE))->toBeTrue()
        ->and(Catalog::isMandatory(Catalog::SOURCE_EXAM_RESIT))->toBeTrue()
        ->and(Catalog::isMandatory(Catalog::SOURCE_ADMISSION_ENROLLMENT))->toBeFalse()
        ->and(Catalog::isMandatory(Catalog::SOURCE_BHYT))->toBeFalse();

    $missingInferenceSources = Catalog::firstPassMissingInferenceSources();

    expect($missingInferenceSources)
        ->not->toContain(Catalog::SOURCE_BHYT)
        ->not->toContain(Catalog::SOURCE_ADMISSION_ENROLLMENT)
        ->toContain(Catalog::SOURCE_TUITION_PLAN)
        ->toContain(Catalog::SOURCE_EGC_TUITION)
        ->toContain(Catalog::SOURCE_COURSE_RETAKE)
        ->toContain(Catalog::SOURCE_EXAM_RESIT);
});

it('requires every registry entry to declare required behaviour metadata', function () {
    foreach (ObligationTypeRegistry::all() as $type => $definition) {
        expect($definition->type)->toBe($type)
            ->and($definition->label)->not->toBe('')
            ->and($definition->pricingStrategy)->not->toBeNull()
            ->and($definition->cancellationPolicy)->not->toBeNull()
            ->and($definition->financialEffect)->toBeInstanceOf(FinancialEffect::class)
            ->and($definition->allowedSourceKinds)->toBeArray();
    }
});
