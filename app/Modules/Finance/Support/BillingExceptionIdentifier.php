<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support;

use InvalidArgumentException;

final class BillingExceptionIdentifier
{
    private const OFFSETS = [
        'missing_charge' => 1_000_000_000,
        'retake_no_charge' => 2_000_000_000,
        'defer_no_case' => 3_000_000_000,
        'zero_tuition_waived' => 4_000_000_000,
        'deferred_enrolled' => 5_000_000_000,
    ];

    public static function encode(string $type, int $sourceId): int
    {
        if (! isset(self::OFFSETS[$type])) {
            throw new InvalidArgumentException("Unknown billing exception type: {$type}");
        }

        return self::OFFSETS[$type] + $sourceId;
    }

    /**
     * @return array{type: string, source_id: int}
     */
    public static function decode(int $exceptionId): array
    {
        $offsets = self::OFFSETS;
        arsort($offsets);

        foreach ($offsets as $type => $offset) {
            if ($exceptionId >= $offset) {
                return [
                    'type' => $type,
                    'source_id' => $exceptionId - $offset,
                ];
            }
        }

        throw new InvalidArgumentException("Unknown billing exception id: {$exceptionId}");
    }
}