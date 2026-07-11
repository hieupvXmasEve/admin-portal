<?php

declare(strict_types=1);

use App\Modules\Finance\Support\SettlementPosition\SettlementBypassAllowlist;

/**
 * @return list<string>
 */
function settlementBypassViolations(string $root): array
{
    $violations = [];
    $allowlist = SettlementBypassAllowlist::entries();
    $patterns = [
        'local settlement formula' => '/(?:amount_snapshot|fc\.amount|finance_charges\.amount|total_amount)\s*-\s*(?:.*(?:paid|payment|discount|credit))/i',
        'direct money-table writer' => '/(?:FinanceCharge|InvoiceLine|InvoiceDiscount|DiscountAllocation|Payment|PaymentApplication|CreditApplication)::(?:query\(\)\s*->\s*)?(?:(?:where|find|findOrFail|first|firstOrFail)\s*\([^;]*?\)\s*->\s*)?(?:create|update|upsert|insert|delete)\s*\(/s',
        'direct money-model instance writer' => '/\$(?:financeCharge|invoiceLine|invoiceDiscount|discountAllocation|paymentApplication|creditApplication|payment)\w*\s*->\s*(?:save|update|delete|increment|decrement)\s*\(/i',
        'direct money-model relation writer' => '/->\s*(?:invoiceLines|invoiceDiscounts|discountAllocations|payments|paymentApplications|creditApplications)\s*\(\s*\)\s*->\s*(?:create|update|delete)\s*\(/',
        'direct money-table query writer' => '/DB::table\s*\(\s*[\'\"](?:finance_charges|invoice_lines|invoice_discounts|discount_allocations|payments|payment_applications|credit_applications)[\'\"]\s*\)\s*->\s*(?:insert|insertGetId|update|upsert|delete)\s*\(/',
        'direct money-table statement writer' => '/DB::(?:statement|unprepared)\s*\(\s*[\'\"][^\'\"]*(?:finance_charges|invoice_lines|invoice_discounts|discount_allocations|payments|payment_applications|credit_applications)[^\'\"]*[\'\"]\s*\)/i',
    ];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $relativePath = 'app/'.str_replace(base_path('app').'/', '', $path);
        $contents = file_get_contents($path) ?: '';

        foreach ($patterns as $label => $pattern) {
            if (preg_match($pattern, $contents) === 1 && ! isset($allowlist[$relativePath])) {
                $violations[] = "{$label}: {$relativePath}";
            }
        }
    }

    sort($violations);

    return $violations;
}

it('requires every legacy settlement bypass to declare an owner and removal wave', function (): void {
    $expectedPaths = [
        'app/Console/Commands/BackfillEgcBlocks.php',
        'app/Console/Commands/MigrateScholarshipDiscountsToInvoiceDiscounts.php',
        'app/Console/Commands/MigrateVoucherDiscountsToInvoiceDiscounts.php',
        'app/Http/Resources/Api/V1/Student/StudentInvoiceResource.php',
        'app/Modules/Finance/Actions/BackfillLegacyDeferCreditEntitlementsAction.php',
        'app/Modules/Finance/Actions/BackfillLegacyEgcExemptCreditEntitlementsAction.php',
        'app/Modules/Finance/Actions/BackfillLegacyScholarshipEntitlementsAction.php',
        'app/Modules/Finance/Actions/CreateBatchDngFromChargesAction.php',
        'app/Modules/Finance/Actions/CreateFinanceChargeAction.php',
        'app/Modules/Finance/Actions/Operations/GenerateNonAcademicChargesAction.php',
        'app/Modules/Finance/Actions/RequestFinanceCreditAction.php',
        'app/Modules/Finance/Actions/RequestFinanceDiscountAction.php',
        'app/Modules/Finance/Queries/Dng/ListDngWorklistQuery.php',
        'app/Modules/Finance/Queries/Reporting/ListCollectionProgressQuery.php',
        'app/Modules/Finance/Services/InvoiceGenerationService.php',
        'app/Modules/Finance/Services/PaymentService.php',
        'app/Modules/Finance/Services/SettlementService.php',
    ];

    $actualPaths = array_keys(SettlementBypassAllowlist::entries());
    sort($actualPaths);
    sort($expectedPaths);

    expect($actualPaths)->toBe($expectedPaths);

    foreach (SettlementBypassAllowlist::entries() as $path => $entry) {
        expect($path)->toStartWith('app/')
            ->and($entry['owner'])->not->toBe('')
            ->and($entry['remove_by_wave'])->toMatch('/^wave-[0-6]$/')
            ->and(file_exists(base_path($path)))->toBeTrue();
    }
});

it('blocks new local settlement formulas and direct money-table writers', function (): void {
    $violations = settlementBypassViolations(base_path('app'));

    expect($violations)->toBe([], "Settlement bypasses outside the reviewed allowlist:\n".implode("\n", $violations));
});
