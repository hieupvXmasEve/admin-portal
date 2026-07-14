<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

/**
 * Temporary, reviewed migration inventory. New entries need a named owner and
 * deletion wave; Wave 6 reduces this list to zero.
 */
final class SettlementBypassAllowlist
{
    /**
     * @return array<string, array{owner: string, remove_by_wave: string, reason: string}>
     */
    public static function entries(): array
    {
        return [
            'app/Console/Commands/BackfillEgcBlocks.php' => self::legacy('Finance', 'wave-6', 'Historical EGC repair writes invoice lines.'),
            'app/Console/Commands/MigrateScholarshipDiscountsToInvoiceDiscounts.php' => self::legacy('Finance', 'wave-6', 'One-shot discount migration.'),
            'app/Console/Commands/MigrateVoucherDiscountsToInvoiceDiscounts.php' => self::legacy('Finance', 'wave-6', 'One-shot voucher migration.'),
            'app/Modules/Finance/Actions/BackfillLegacyDeferCreditEntitlementsAction.php' => self::legacy('Finance', 'wave-6', 'Legacy entitlement backfill.'),
            'app/Modules/Finance/Actions/BackfillLegacyEgcExemptCreditEntitlementsAction.php' => self::legacy('Finance', 'wave-6', 'Legacy EGC exemption backfill.'),
            'app/Modules/Finance/Actions/BackfillLegacyScholarshipEntitlementsAction.php' => self::legacy('Finance', 'wave-6', 'Legacy scholarship backfill.'),
            'app/Modules/Finance/Actions/CreateFinanceChargeAction.php' => self::legacy('Finance', 'wave-6', 'Approved debit materializer pending mutation guard.'),
            'app/Modules/Finance/Actions/Operations/GenerateNonAcademicChargesAction.php' => self::legacy('Finance', 'wave-5', 'Legacy non-academic charge generator.'),
            'app/Modules/Finance/Actions/RequestFinanceCreditAction.php' => self::legacy('Finance', 'wave-1', 'Canonical credit request will enter mutation guard.'),
            'app/Modules/Finance/Actions/RequestFinanceDiscountAction.php' => self::legacy('Finance', 'wave-1', 'Canonical discount request will enter mutation guard.'),
            'app/Modules/Finance/Actions/CreateBatchDngFromChargesAction.php' => self::legacy('Finance', 'wave-1', 'DNG request amount formula pending guarded reservation.'),
            'app/Modules/Finance/Services/InvoiceGenerationService.php' => self::legacy('Finance', 'wave-4', 'Legacy invoice-line materializer pending mutation guard.'),
            'app/Modules/Finance/Services/PaymentService.php' => self::legacy('Finance', 'wave-1', 'Legacy receipt and application writer.'),
            'app/Modules/Finance/Services/SettlementService.php' => self::legacy('Finance', 'wave-4', 'Legacy settlement writer and formula consumer.'),
        ];
    }

    /**
     * @return array{owner: string, remove_by_wave: string, reason: string}
     */
    private static function legacy(string $owner, string $removeByWave, string $reason): array
    {
        return [
            'owner' => $owner,
            'remove_by_wave' => $removeByWave,
            'reason' => $reason,
        ];
    }
}
