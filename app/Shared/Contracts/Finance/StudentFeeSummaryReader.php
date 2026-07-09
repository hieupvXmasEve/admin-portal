<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

/**
 * Cross-module read for the Academic Hub student fee statement tab.
 *
 * Finance owns the money tables; Academic consumes this contract instead of
 * joining invoices/charges/payments directly (ADR-0026 boundary).
 */
interface StudentFeeSummaryReader
{
    /**
     * @return array<string, mixed>
     */
    public function execute(int $studentId): array;
}
