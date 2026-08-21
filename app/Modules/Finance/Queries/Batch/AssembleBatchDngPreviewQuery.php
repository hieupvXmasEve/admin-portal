<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Batch;

use App\Modules\Finance\Queries\Dng\ListDngWorklistQuery;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AssembleBatchDngPreviewQuery
{
    public function __construct(private readonly ListDngWorklistQuery $worklist) {}

    /**
     * @param  int[]  $studentIds
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    public function handle(int $semesterId, string $dngFeeType, array $studentIds, ?int $campusId): array
    {
        $request = Request::create('', 'GET', array_filter([
            'semester_id' => $semesterId,
            'dng_fee_type' => $dngFeeType,
            'campus_id' => $campusId,
            'per_page' => 200,
        ]));

        $worklist = $this->worklist->handle($request);
        $paginator = $worklist['students'] ?? null;
        $rows = $this->extractRows($paginator);
        // per_page is capped at 200 above; a worklist bigger than that would
        // otherwise silently drop students from the preview (plan.md phase 3
        // risk table — "must not be invisible").
        $truncated = $paginator instanceof LengthAwarePaginator && $paginator->total() > count($rows);

        // Automatic replacement is a config-gated feature
        // (config/finance.php: dng.auto_replace_stale_collection, default
        // off — plan.md Rollback). Off means DngReservationLifecycle::reserve()
        // will refuse to replace and hold the request for review instead, so a
        // 'replace' offer here would be a broken promise the commit cannot
        // keep. Uncovered-but-fully-covered ('skip') is unaffected — nothing
        // to replace there regardless of the flag.
        $autoReplaceEnabled = (bool) config('finance.dng.auto_replace_stale_collection', false);

        if ($studentIds !== []) {
            $allowed = array_flip(array_map('intval', $studentIds));
            $rows = array_values(array_filter(
                $rows,
                fn (array $row) => isset($allowed[(int) ($row['student_id'] ?? 0)]),
            ));
        }

        $lines = [];
        foreach ($rows as $row) {
            $studentId = (int) ($row['student_id'] ?? 0);
            $chargeIds = array_map('intval', array_column($row['charges'] ?? [], 'id'));
            sort($chargeIds);
            $total = (float) ($row['next_push_amount'] ?? $row['balance'] ?? 0);
            $hasActiveDng = ! empty($row['active_dng']);
            $coverageKnown = $row['coverage_known'] ?? null;
            $uncoveredAmount = $row['uncovered_amount'] ?? null;

            // Four states (plan.md phase 3): no live collection → create; live
            // collection but coverage isn't computable (no semester scope, no
            // DNG campus mapping, wrong status, or a legacy request with no
            // reservation targets — H14) → blocked, never replace; fully
            // covered → skip; otherwise → replace, but only when automatic
            // replacement is actually enabled — cancel-then-push
            // (DngReservationLifecycle::reserve()) is what runs once the row
            // is selected and committed, and it no-ops to holdForReview when
            // the flag is off (plan.md Rollback).
            [$diff, $reason] = match (true) {
                ! $hasActiveDng => ['create', null],
                $coverageKnown === false || $coverageKnown === null => ['blocked', 'active_dng_coverage_unknown'],
                (float) $uncoveredAmount <= 0.0 => ['skip', 'active_dng_already_covers_payable'],
                ! $autoReplaceEnabled => ['blocked', 'active_dng_replacement_disabled'],
                default => ['replace', 'active_dng_replacement_required'],
            };
            // The shared Batch Studio wizard/table only know 4 generic buckets
            // (create/update/skip/warning) — map our DNG-specific states onto
            // them rather than widening a component shared with every other
            // batch job.
            $bucket = match ($diff) {
                'replace' => 'update',
                'blocked' => 'warning',
                default => $diff,
            };

            $lines[] = new BatchPreviewLine(
                key: sprintf('dng:student:%d:fee:%s', $studentId, $dngFeeType),
                hashPayload: [
                    'student_id' => $studentId,
                    'dng_fee_type' => $dngFeeType,
                    'charge_ids' => $chargeIds,
                    'net' => $total,
                    'has_active_dng' => $hasActiveDng,
                    'uncovered_amount' => $uncoveredAmount,
                    'coverage_known' => $coverageKnown,
                ],
                display: [
                    'student_id' => (string) ($row['student_code'] ?? ''),
                    'label' => (string) ($row['student_name'] ?? ''),
                    'diff' => $bucket,
                    'net' => $total,
                    'installment_aware_total' => $total,
                    'uncovered_amount' => $uncoveredAmount,
                    'reason' => $reason,
                    'warning_codes' => $reason !== null ? [$reason] : [],
                ],
            );
        }

        return ['lines' => $lines, 'summary' => [
            'total_students' => count($lines),
            'total_amount' => array_sum(array_map(fn ($l) => $l->display['net'], $lines)),
            'truncated' => $truncated,
        ]];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractRows(mixed $students): array
    {
        if ($students instanceof LengthAwarePaginator) {
            return $students->items();
        }

        if (is_array($students)) {
            return $students;
        }

        return [];
    }
}
