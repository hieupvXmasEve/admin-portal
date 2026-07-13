<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\SettlementPosition;

/**
 * Maps the Finance integrity registry into the Settlement Position vocabulary.
 *
 * The catalog is deliberately one-way: the shadow check reports canonical
 * evidence and its integrity findings; it never asks a legacy balance consumer
 * to decide whether the canonical result is correct.
 */
final class SettlementPositionIssueCatalog
{
    /**
     * @var array<string, string>
     */
    private const INVARIANT_ISSUES = [
        'INV-1' => 'settlement_position.finance_invariant.payment_over_allocated',
        'INV-2' => 'settlement_position.finance_invariant.invoice_cache_drift',
        'INV-3' => 'settlement_position.finance_invariant.active_payable_line_cardinality',
        'INV-4' => 'settlement_position.finance_invariant.payment_on_void_line',
        'INV-5' => 'settlement_position.finance_invariant.reversed_discount_residue',
        'INV-6' => 'settlement_position.finance_invariant.duplicate_invoice',
        'INV-7' => 'settlement_position.finance_invariant.duplicate_scholarship',
        'INV-8' => 'settlement_position.finance_invariant.negative_raw_remaining',
        'INV-9' => 'settlement_position.finance_invariant.duplicate_invoice_number',
        'INV-10' => 'settlement_position.finance_invariant.non_positive_payment',
        'INV-11' => 'settlement_position.finance_invariant.duplicate_dng_webhook',
        'INV-12' => 'settlement_position.finance_invariant.dng_payment_amount_drift',
        'INV-13' => 'settlement_position.finance_invariant.live_installment_on_void_charge',
        'INV-14' => 'settlement_position.finance_invariant.dng_header_breakdown_drift',
        'INV-15' => 'settlement_position.finance_invariant.dng_installment_breakdown_drift',
        'INV-16' => 'settlement_position.finance_invariant.active_signed_adjustment',
        'INV-17' => 'settlement_position.finance_invariant.invoice_scope_mismatch',
        'INV-18' => 'settlement_position.finance_invariant.active_line_on_void_charge',
    ];

    /**
     * @var array<string, string>
     */
    private const POSITION_INVARIANTS = [
        SettlementPositionIssue::DUPLICATE_ACTIVE_PAYABLE_LINES => 'INV-3',
        SettlementPositionIssue::FINANCE_CHARGE_NOT_ACTIVE => 'INV-4',
        SettlementPositionIssue::REVERSED_DISCOUNT_RESIDUE => 'INV-5',
        SettlementPositionIssue::CASH_EXCEEDS_NET_DUE => 'INV-8',
        SettlementPositionIssue::CREDIT_EXCEEDS_REMAINING => 'INV-8',
        SettlementPositionIssue::NEGATIVE_RAW_REMAINING => 'INV-8',
    ];

    public static function issueForInvariant(string $invariantCode): ?string
    {
        return self::INVARIANT_ISSUES[$invariantCode] ?? null;
    }

    public static function invariantForIssue(string $issueCode): ?string
    {
        return self::POSITION_INVARIANTS[$issueCode] ?? null;
    }

    /**
     * @return array<string, string>
     */
    public static function invariantIssues(): array
    {
        return self::INVARIANT_ISSUES;
    }
}
