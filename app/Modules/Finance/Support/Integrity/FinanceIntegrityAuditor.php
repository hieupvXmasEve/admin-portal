<?php

declare(strict_types=1);

namespace App\Modules\Finance\Support\Integrity;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs the shared finance invariants globally (for the console command) or
 * scoped to a set of student ids (for the audit workspace).
 *
 * Error policy: a failing invariant SQL is surfaced, never swallowed into a
 * clean "0 offending" result. summarize() reports count=null + error; the
 * command renders ERROR; findForScope() emits kind=invariant_error so the
 * workspace shows "audit unavailable".
 */
class FinanceIntegrityAuditor
{
    public function __construct(private readonly FinanceInvariantRegistry $registry) {}

    /**
     * Global/summary run. A failing invariant yields count=null + error message
     * (never 0) so the command can still render ERROR — parity preserved.
     *
     * @return list<array{code:string,severity:string,label:string,count:?int,error:?string}>
     */
    public function summarize(?FinanceAuditScope $scope = null): array
    {
        return array_map(function (FinanceInvariant $invariant) use ($scope): array {
            try {
                $count = $this->count($invariant, $scope);
                $error = null;
            } catch (Throwable $e) {
                $count = null;
                $error = $e->getMessage();
            }

            return [
                'code' => $invariant->code,
                'severity' => $invariant->severity,
                'label' => $invariant->label,
                'count' => $count,
                'error' => $error,
            ];
        }, $this->registry->all());
    }

    /**
     * Raw count. Deliberately does NOT swallow DB errors — a broken invariant SQL
     * must surface as a failure, not as "0 offending rows".
     */
    public function count(FinanceInvariant $invariant, ?FinanceAuditScope $scope = null): int
    {
        $sql = $this->resolve($invariant->countSqlTemplate, $invariant, $scope);

        return (int) (DB::selectOne($sql)->c ?? 0);
    }

    /**
     * Best-effort sample ids — only ever called after count() already succeeded,
     * so an empty list here is decoration loss, not a hidden integrity failure.
     *
     * @return list<int>
     */
    public function samples(FinanceInvariant $invariant, ?FinanceAuditScope $scope = null): array
    {
        $sql = $this->resolve($invariant->sampleSqlTemplate, $invariant, $scope);

        try {
            return array_values(array_map(static fn ($row): int => (int) $row->id, DB::select($sql)));
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Subject-scoped findings. Emits a finding for every invariant that is either
     * violated (kind=invariant) or failed to run (kind=invariant_error). Empty
     * scope returns [] (never a global scan).
     *
     * @return list<array{code:string,severity:string,label:string,kind:string,sample_ids:list<int>}>
     */
    public function findForScope(FinanceAuditScope $scope): array
    {
        if ($scope->isEmpty()) {
            return [];
        }

        $findings = [];
        foreach ($this->registry->all() as $invariant) {
            try {
                $count = $this->count($invariant, $scope);
            } catch (Throwable) {
                $findings[] = [
                    'code' => $invariant->code,
                    'severity' => 'ERROR',
                    'label' => $invariant->label,
                    'kind' => 'invariant_error',
                    'sample_ids' => [],
                ];

                continue;
            }

            if ($count > 0) {
                $findings[] = [
                    'code' => $invariant->code,
                    'severity' => $invariant->severity,
                    'label' => $invariant->label,
                    'kind' => 'invariant',
                    'sample_ids' => $this->samples($invariant, $scope),
                ];
            }
        }

        return $findings;
    }

    private function resolve(string $template, FinanceInvariant $invariant, ?FinanceAuditScope $scope): string
    {
        if ($scope === null || $scope->isEmpty()) {
            return str_replace('{scope}', '1=1', $template);
        }

        // studentIdsCsv() is sanitized to positive ints only — safe to interpolate.
        $predicate = str_replace('{ids}', $scope->studentIdsCsv(), $invariant->studentScopePredicate);

        return str_replace('{scope}', $predicate, $template);
    }
}
