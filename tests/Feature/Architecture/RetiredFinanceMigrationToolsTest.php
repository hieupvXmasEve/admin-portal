<?php

declare(strict_types=1);

use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Support\Entitlement\FinanceEntitlementType;
use Illuminate\Contracts\Console\Kernel;

it('keeps active charge creation types separate from entitlement types', function (): void {
    expect(FinanceCharge::CHARGE_TYPES)->toEqualCanonicalizing([
        FinanceCharge::TYPE_TUITION_TERM,
        FinanceCharge::TYPE_EGC_LEVEL_FEE,
        FinanceCharge::TYPE_RETAKE_FEE,
        FinanceCharge::TYPE_EXAM_RESIT_FEE,
        FinanceCharge::TYPE_MANUAL_FEE,
        FinanceCharge::TYPE_ADMISSION_FEE,
        FinanceCharge::TYPE_ADJUSTMENT,
        FinanceCharge::TYPE_BHYT,
    ])
        ->and(FinanceEntitlementType::CREDIT_TYPES)->toEqualCanonicalizing([
            FinanceEntitlementType::DeferCredit,
            FinanceEntitlementType::EgcExemptCredit,
            FinanceEntitlementType::ScholarshipCredit,
        ])
        ->and(FinanceEntitlementType::DISCOUNT_TYPES)->toEqualCanonicalizing([
            FinanceEntitlementType::ScholarshipCredit,
            FinanceEntitlementType::VoucherCredit,
        ]);
});

it('does not register one-time Finance migration commands', function (): void {
    $commands = array_keys(app(Kernel::class)->all());

    foreach ([
        'finance:backfill-billing-accounts',
        'finance:backfill-fees',
        'finance:backfill-legacy-bhyt-obligations',
        'finance:backfill-legacy-defer-credit-entitlements',
        'finance:backfill-legacy-egc-exempt-credit-entitlements',
        'finance:backfill-legacy-egc-level-fee-obligations',
        'finance:backfill-legacy-egc-retake-discount-entitlements',
        'finance:backfill-legacy-retake-resit-obligations',
        'finance:backfill-legacy-scholarship-entitlements',
        'finance:backfill-legacy-tuition-term-obligations',
        'finance:backfill-legacy-voucher-discount-entitlements',
        'finance:backfill-voided-charge-installments',
        'finance:close-known-legacy-data-exceptions',
        'finance:defer-backfill',
        'finance:migrate-scholarship-discounts',
        'finance:migrate-voucher-discounts',
        'egc:backfill-blocks',
        'academic:backfill-legacy-exam-resit-completion',
        'academic:backfill-legacy-exam-resit-schedule',
        'academic-records:fix-retake-data',
    ] as $command) {
        expect($commands)->not->toContain($command);
    }
});

it('finds no production caller for retired migration interfaces', function (): void {
    $productionPaths = [base_path('app'), base_path('bootstrap'), base_path('config'), base_path('routes')];
    $retiredReferences = [
        'finance:backfill-billing-accounts',
        'finance:backfill-fees',
        'finance:backfill-legacy-',
        'finance:backfill-voided-charge-installments',
        'finance:close-known-legacy-data-exceptions',
        'finance:defer-backfill',
        'finance:migrate-scholarship-discounts',
        'finance:migrate-voucher-discounts',
        'egc:backfill-blocks',
        'academic:backfill-legacy-exam-resit-',
        'academic-records:fix-retake-data',
    ];

    $matches = [];

    foreach ($productionPaths as $path) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path)) as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $contents = file_get_contents($file->getPathname()) ?: '';

            foreach ($retiredReferences as $reference) {
                if (str_contains($contents, $reference)) {
                    $matches[] = $file->getPathname().': '.$reference;
                }
            }
        }
    }

    expect($matches)->toBe([]);
});

it('does not autoload one-time Finance migration actions', function (): void {
    foreach ([
        'App\\Modules\\Finance\\Actions\\BackfillBillingAccountsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyBhytObligationsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyDeferCreditEntitlementsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyEgcExemptCreditEntitlementsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyEgcLevelFeeObligationsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyEgcRetakeDiscountEntitlementsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyRetakeResitObligationsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyScholarshipEntitlementsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyTuitionTermObligationsAction',
        'App\\Modules\\Finance\\Actions\\BackfillLegacyVoucherDiscountEntitlementsAction',
        'App\\Modules\\Finance\\Actions\\CloseKnownLegacyDataExceptionsAction',
        'App\\Modules\\Finance\\Actions\\Operations\\BackfillDeferCaseItemsAction',
        'App\\Modules\\Academic\\Actions\\BackfillLegacyExamResitCompletionAction',
        'App\\Modules\\Academic\\Actions\\BackfillLegacyExamResitScheduleAction',
    ] as $class) {
        expect(class_exists($class))->toBeFalse();
    }
});
