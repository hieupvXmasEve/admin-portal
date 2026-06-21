<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

/**
 * Subject scope for finance integrity checks. Every searched subject
 * (student/invoice/payment/DNG/charge) is resolved to its owning student id set
 * upstream (GetFinanceAuditGraphQuery), so the scope only carries student ids.
 * If a future invariant genuinely needs entity-level scoping, add the id type
 * here together with its registry predicate — do not add unused fields now.
 */
final class FinanceAuditScope
{
    /**
     * @param  list<int>  $studentIds
     */
    public function __construct(
        public readonly array $studentIds = [],
        public readonly ?int $semesterId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->studentIdsCsv() === '' && $this->semesterId === null;
    }

    public function studentIdsCsv(): string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $this->studentIds),
            static fn (int $id): bool => $id > 0,
        )));
        sort($ids);

        return implode(',', $ids);
    }
}
