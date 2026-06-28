<?php

declare(strict_types=1);

namespace App\Shared\Contracts\Finance;

/**
 * Cross-module read contract the Student Hub uses to render its read-only
 * Finance tab (ADR-0007). The Academic Hub is finance-aware but not
 * finance-owning: it consumes this contract instead of joining into Finance
 * tables, so the Finance module keeps sole ownership of money data.
 *
 * Implementations expose reads only — there is no mutation surface here.
 */
interface HubStudentFinanceSummaryReader
{
    /**
     * Read-only finance summary for one student: fees, gold wallet, and
     * scholarships. Returns zeroed/empty sections rather than throwing when a
     * student has no finance records yet.
     *
     * @return array{
     *     fees: array{net_charges: float, total_paid: float, outstanding: float, unapplied_credit: float, status: string},
     *     gold: array{balance: int},
     *     scholarships: array<int, array{code: string, name: string|null, type: string|null, amount: float, awarded_at: string|null}>
     * }
     */
    public function summary(int $studentId): array;
}
