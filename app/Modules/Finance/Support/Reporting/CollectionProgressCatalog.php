<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Reporting;

/**
 * Vocabulary for the Collection Progress reporting lens (FIN-REV-018).
 *
 * Holds the canonical balance-state and aging/due-bucket definitions shared by
 * the query, summary, request validation, and filter options so the values can
 * never drift between the API contract and the predicates that classify rows.
 */
final class CollectionProgressCatalog
{
    /**
     * Money comparison tolerance. Mirrors SettlementService::CACHE_DRIFT_TOLERANCE
     * so a sub-cent rounding residue never flips a row's balance state.
     */
    public const TOLERANCE = 0.01;

    public const STATE_UNPAID = 'unpaid';

    public const STATE_PARTIALLY_PAID = 'partially_paid';

    public const STATE_PAID = 'paid';

    public const STATE_OVERDUE = 'overdue';

    public const STATE_OVERPAID = 'overpaid';

    public const STATE_UNAPPLIED = 'unapplied';

    public const BUCKET_NOT_DUE = 'not_due';

    public const BUCKET_1_30 = 'd_1_30';

    public const BUCKET_31_60 = 'd_31_60';

    public const BUCKET_61_90 = 'd_61_90';

    public const BUCKET_90_PLUS = 'd_90_plus';

    /**
     * Balance-state filter vocabulary from FIN-REV-012.
     *
     * @return array<string, string>
     */
    public static function balanceStates(): array
    {
        return [
            self::STATE_UNPAID => 'Chưa thanh toán',
            self::STATE_PARTIALLY_PAID => 'Thanh toán một phần',
            self::STATE_PAID => 'Đã thanh toán',
            self::STATE_OVERDUE => 'Quá hạn',
            self::STATE_OVERPAID => 'Thanh toán dư',
            self::STATE_UNAPPLIED => 'Tiền chưa áp dụng',
        ];
    }

    /**
     * @return list<string>
     */
    public static function balanceStateKeys(): array
    {
        return array_keys(self::balanceStates());
    }

    /**
     * Aging/due-bucket vocabulary keyed by inclusive day range. `not_due` covers
     * everything that is not past due (paid in full or still within due date).
     *
     * @return array<string, array{label: string, min: int|null, max: int|null}>
     */
    public static function agingBuckets(): array
    {
        return [
            self::BUCKET_NOT_DUE => ['label' => 'Chưa quá hạn', 'min' => null, 'max' => 0],
            self::BUCKET_1_30 => ['label' => 'Quá hạn 1-30 ngày', 'min' => 1, 'max' => 30],
            self::BUCKET_31_60 => ['label' => 'Quá hạn 31-60 ngày', 'min' => 31, 'max' => 60],
            self::BUCKET_61_90 => ['label' => 'Quá hạn 61-90 ngày', 'min' => 61, 'max' => 90],
            self::BUCKET_90_PLUS => ['label' => 'Quá hạn trên 90 ngày', 'min' => 91, 'max' => null],
        ];
    }

    /**
     * @return list<string>
     */
    public static function agingBucketKeys(): array
    {
        return array_keys(self::agingBuckets());
    }

    /**
     * Classify the worst overdue age (in whole days) into an aging bucket.
     * Zero or negative days are never past due, so they fall in `not_due`.
     */
    public static function classifyAging(int $daysOverdue): string
    {
        if ($daysOverdue <= 0) {
            return self::BUCKET_NOT_DUE;
        }

        foreach (self::agingBuckets() as $key => $range) {
            if ($key === self::BUCKET_NOT_DUE) {
                continue;
            }

            $withinFloor = $range['min'] === null || $daysOverdue >= $range['min'];
            $withinCeil = $range['max'] === null || $daysOverdue <= $range['max'];

            if ($withinFloor && $withinCeil) {
                return $key;
            }
        }

        return self::BUCKET_90_PLUS;
    }

    public static function agingBucketLabel(string $bucket): string
    {
        return self::agingBuckets()[$bucket]['label'] ?? $bucket;
    }

    public static function balanceStateLabel(string $state): string
    {
        return self::balanceStates()[$state] ?? $state;
    }
}
