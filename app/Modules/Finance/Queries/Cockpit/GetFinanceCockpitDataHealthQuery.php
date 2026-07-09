<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Cockpit;

use App\Models\Student;
use App\Modules\Finance\Models\StudentInvoice;
use App\Modules\Finance\Services\SettlementService;
use App\Modules\Finance\Support\Integrity\FinanceAuditScope;
use App\Modules\Finance\Support\Integrity\FinanceIntegrityAuditor;

/**
 * §4.4 data-health: the 15 invariants as trust signals + the "Số dư khớp" ratio.
 * Campus-scoped by default (student-id scope); all-campus runs the auditor globally.
 * Deferred + manually refreshed — NOT polled — because the campus-scoped invariant
 * SQL and the snapshot drift iteration are heavier than the queue counts.
 */
class GetFinanceCockpitDataHealthQuery
{
    public function __construct(
        private FinanceIntegrityAuditor $auditor,
        private SettlementService $settlement,
    ) {}

    /** @return array<string,mixed> */
    public function handle(?int $campusId, bool $allCampus, ?int $semesterId = null): array
    {
        [$scope, $studentIds, $scopeBadge] = $this->resolveScope($campusId, $allCampus, $semesterId);

        $summary = $this->auditor->summarize($scope);

        $invariants = array_values(array_filter(array_map(function (array $row): ?array {
            $count = $row['count'];
            if ($count === null) {
                return ['code' => $row['code'], 'severity' => 'ERROR', 'label' => $row['label'], 'count' => null, 'error' => $row['error']];
            }
            if ($count <= 0) {
                return null;
            }

            return ['code' => $row['code'], 'severity' => $row['severity'], 'label' => $row['label'], 'count' => (int) $count, 'error' => null];
        }, $summary)));

        $criticalCount = array_sum(array_map(
            fn (array $i) => $i['severity'] === 'CRITICAL' ? $i['count'] : 0,
            array_filter($invariants, fn (array $i) => $i['count'] !== null),
        ));

        return [
            'scope_badge' => $scopeBadge,
            'critical_count' => (int) $criticalCount,
            'invariants' => $invariants,
            'balance_match' => $this->balanceMatch($studentIds, $allCampus, $semesterId),
        ];
    }

    /**
     * @return array{0:?FinanceAuditScope,1:?list<int>,2:string}
     */
    private function resolveScope(?int $campusId, bool $allCampus, ?int $semesterId): array
    {
        if ($allCampus) {
            if ($semesterId !== null) {
                return [new FinanceAuditScope(semesterId: $semesterId), null, 'semester'];
            }

            return [null, null, 'all_campus'];
        }

        if ($campusId === null) {
            return [new FinanceAuditScope(semesterId: $semesterId), [], 'campus'];
        }

        $ids = Student::query()->where('campus_id', $campusId)->pluck('id')->map(fn ($id) => (int) $id)->all();

        return [new FinanceAuditScope($ids, $semesterId), $ids, 'campus'];
    }

    /**
     * @param  list<int>|null  $studentIds
     * @return array{match_pct:float,matched:int,denominator:int}
     */
    private function balanceMatch(?array $studentIds, bool $allCampus, ?int $semesterId): array
    {
        $query = StudentInvoice::query()
            ->with(['invoiceLines.charge', 'invoiceLines.paymentApplications', 'invoiceLines.discountAllocations']);

        if ($semesterId !== null) {
            $query->where('semester_id', $semesterId);
        }

        if (! $allCampus) {
            $query->whereIn('student_id', $studentIds ?? []);
        }

        $invoices = $query->get();
        $denominator = $invoices->count();
        if ($denominator === 0) {
            return ['match_pct' => 100.0, 'matched' => 0, 'denominator' => 0];
        }

        $matched = $invoices->reject(fn (StudentInvoice $inv) => $this->settlement->invoiceCacheDrifts($inv))->count();

        return [
            'match_pct' => round(($matched / $denominator) * 100, 1),
            'matched' => $matched,
            'denominator' => $denominator,
        ];
    }
}
