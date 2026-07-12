<?php

declare(strict_types=1);

namespace App\Modules\Finance\Dng\Support;

final class DngReservationTargetFingerprint
{
    /** @param list<array<string, mixed>> $targets */
    public static function make(array $targets): string
    {
        $normalized = array_map(static fn (array $target): array => [
            'invoice_line_id' => (int) $target['invoice_line_id'],
            'finance_charge_id' => (int) $target['finance_charge_id'],
            'collectible' => (string) $target['collectible'],
            'identity' => (string) ($target['identity'] ?? $target['target_identity']),
        ], $targets);

        return hash('sha256', json_encode($normalized, JSON_THROW_ON_ERROR));
    }
}
