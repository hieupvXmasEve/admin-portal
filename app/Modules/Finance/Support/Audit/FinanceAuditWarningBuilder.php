<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Audit;

use App\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;

/**
 * Maps a student scope into UI warnings:
 *  (a) shared invariant findings via FinanceIntegrityAuditor::findForScope() —
 *      passed through verbatim, preserving kind ('invariant' for a violation,
 *      'invariant_error' for a check that failed to run);
 *  (b) cache-drift warnings, computed from SettlementService (canonical balance)
 *      vs the cached invoice columns.
 *
 * Cache drift uses SettlementService while INV-2 remains the command's DB-level
 * guard — the overlap is intentional (two separate truth layers).
 */
class FinanceAuditWarningBuilder
{
    public function __construct(
        private readonly FinanceIntegrityAuditor $auditor,
        private readonly SettlementService $settlement,
    ) {}

    /**
     * @return list<array{code:string,severity:string,label:string,kind:string,sample_ids:list<int>}>
     */
    public function build(FinanceAuditScope $scope): array
    {
        if ($scope->isEmpty()) {
            return [];
        }

        // (a) Invariant findings — already Warning-shaped, kind preserved.
        $warnings = $this->auditor->findForScope($scope);

        // (b) Cache drift against the canonical SettlementService balance.
        $driftIds = $this->driftingInvoiceIds($scope);
        if ($driftIds !== []) {
            $warnings[] = [
                'code' => 'CACHE-DRIFT',
                'severity' => 'HIGH',
                'label' => 'Invoice cached balance drifts from SettlementService-derived balance',
                'kind' => 'cache_drift',
                'sample_ids' => $driftIds,
            ];
        }

        return $warnings;
    }

    /** @return list<int> */
    private function driftingInvoiceIds(FinanceAuditScope $scope): array
    {
        $studentIds = $this->studentIds($scope);
        if ($studentIds === []) {
            return [];
        }

        $invoices = StudentInvoice::query()
            ->whereIn('student_id', $studentIds)
            ->with([
                'invoiceLines.charge',
                'invoiceLines.paymentApplications',
                'invoiceLines.discountAllocations.invoiceDiscount',
            ])
            ->get();

        $ids = [];
        foreach ($invoices as $invoice) {
            if ($this->settlement->invoiceCacheDrifts($invoice)) {
                $ids[] = (int) $invoice->id;
            }
        }

        return $ids;
    }

    /** @return list<int> sanitized positive student ids from the scope. */
    private function studentIds(FinanceAuditScope $scope): array
    {
        $csv = $scope->studentIdsCsv();

        return $csv === '' ? [] : array_map('intval', explode(',', $csv));
    }
}
