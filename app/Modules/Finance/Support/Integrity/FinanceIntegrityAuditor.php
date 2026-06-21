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
        if ($scope === null) {
            return str_replace('{scope}', '1=1', $template);
        }

        if ($scope->isEmpty()) {
            return str_replace('{scope}', '1=1', $template);
        }

        return str_replace('{scope}', $this->buildScopeClause($invariant, $scope), $template);
    }

    private function buildScopeClause(FinanceInvariant $invariant, FinanceAuditScope $scope): string
    {
        $studentIdsCsv = $scope->studentIdsCsv();
        $semesterId = $scope->semesterId;

        if ($studentIdsCsv === '') {
            if ($semesterId === null) {
                return '1=1';
            }

            return $this->semesterFilterFragment($invariant->code, $semesterId) ?? '1=1';
        }

        $predicate = str_replace('{ids}', $studentIdsCsv, $invariant->studentScopePredicate);

        if ($semesterId === null) {
            return $predicate;
        }

        $semesterFragment = $this->semesterFilterFragment($invariant->code, $semesterId);

        if ($semesterFragment === null) {
            return $predicate;
        }

        return "({$predicate}) AND ({$semesterFragment})";
    }

    private function semesterFilterFragment(string $code, int $semesterId): ?string
    {
        $id = (int) $semesterId;

        return match ($code) {
            'INV-2' => "si.semester_id = {$id}",
            'INV-3', 'INV-8', 'INV-13' => "fc.semester_id = {$id}",
            'INV-6', 'INV-9' => "semester_id = {$id}",
            'INV-4' => "il.invoice_id IN (SELECT id FROM student_invoices WHERE semester_id = {$id})",
            'INV-5' => "idc.invoice_id IN (SELECT id FROM student_invoices WHERE semester_id = {$id})",
            'INV-12', 'INV-14' => "dpr.semester_id = {$id}",
            'INV-1' => "p.student_id IN (SELECT DISTINCT student_id FROM student_invoices WHERE semester_id = {$id})",
            'INV-10' => "student_id IN (SELECT DISTINCT student_id FROM student_invoices WHERE semester_id = {$id})",
            'INV-11' => "dng_payment_request_id IN (SELECT id FROM dng_payment_requests WHERE semester_id = {$id})",
            'INV-15' => "dprc.dng_payment_request_id IN (SELECT id FROM dng_payment_requests WHERE semester_id = {$id})",
            default => null,
        };
    }
}
