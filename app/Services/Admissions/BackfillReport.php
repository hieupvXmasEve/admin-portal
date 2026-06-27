<?php

declare(strict_types=1);

namespace App\Services\Admissions;

/**
 * Immutable outcome of an {@see ApplicationBackfillService} run.
 *
 * `applied === false` means the run was a dry-run: every write was exercised
 * inside a transaction that was then rolled back, so the counts below describe
 * exactly what a real `--apply` run would change without having changed anything.
 */
final readonly class BackfillReport
{
    public function __construct(
        public bool $applied,
        public int $applicationsTotal,
        public int $catalogSynced,
        public int $guardiansCreated,
        public int $documentsFromCsv,
        public int $documentsFromLegacy,
        public int $statusToEnrolled,
        public int $statusToPending,
        public int $applicationsWithParentData,
        public int $applicationsMatchedInCsv,
    ) {}

    public function documentsCreated(): int
    {
        return $this->documentsFromCsv + $this->documentsFromLegacy;
    }

    /**
     * Operator-facing rows for a console summary table.
     *
     * @return array<int, array{0: string, 1: int|string}>
     */
    public function toRows(): array
    {
        return [
            ['Applications scanned', $this->applicationsTotal],
            ['Document types synced (catalog)', $this->catalogSynced],
            ['Guardians created (from parent_*)', $this->guardiansCreated],
            ['  applications with parent data', $this->applicationsWithParentData],
            ['Documents created (from CSV)', $this->documentsFromCsv],
            ['  applications matched in CSV', $this->applicationsMatchedInCsv],
            ['Documents created (submitted_* fallback)', $this->documentsFromLegacy],
            ['Status → enrolled (converted)', $this->statusToEnrolled],
            ['Status → pending (unconverted)', $this->statusToPending],
        ];
    }
}
