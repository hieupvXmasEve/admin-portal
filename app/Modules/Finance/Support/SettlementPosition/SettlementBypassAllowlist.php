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
            'app/Console/Commands/BackfillStudentFees.php' => self::legacy('Finance', 'wave-6', 'Historical student-fee migration.'),
            'app/Console/Commands/BackfillVoidedChargeInstallments.php' => self::legacy('Finance', 'wave-6', 'Historical voided-installment repair.'),
            'app/Modules/Finance/Actions/BackfillLegacyDeferCreditEntitlementsAction.php' => self::legacy('Finance', 'wave-6', 'Legacy entitlement backfill.'),
            'app/Modules/Finance/Actions/BackfillLegacyEgcExemptCreditEntitlementsAction.php' => self::legacy('Finance', 'wave-6', 'Legacy EGC exemption backfill.'),
            'app/Modules/Finance/Actions/BackfillLegacyScholarshipEntitlementsAction.php' => self::legacy('Finance', 'wave-6', 'Legacy scholarship backfill.'),
            'app/Modules/Finance/Actions/CloseKnownLegacyDataExceptionsAction.php' => self::legacy('Finance', 'wave-0', 'Approved one-shot canonical data closure.'),
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
