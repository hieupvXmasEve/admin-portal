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
        $rows = $this->extractRows($worklist['students'] ?? []);

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

            $lines[] = new BatchPreviewLine(
                key: sprintf('dng:student:%d:fee:%s', $studentId, $dngFeeType),
                hashPayload: [
                    'student_id' => $studentId,
                    'dng_fee_type' => $dngFeeType,
                    'charge_ids' => $chargeIds,
                    'net' => $total,
                    'has_active_dng' => $hasActiveDng,
                ],
                display: [
                    'student_id' => (string) ($row['student_code'] ?? ''),
                    'label' => (string) ($row['student_name'] ?? ''),
                    'diff' => $hasActiveDng ? 'skip' : 'create',
                    'net' => $total,
                    'installment_aware_total' => $total,
                    'reason' => $hasActiveDng ? 'active_dng_resolution_required' : null,
                    'warning_codes' => $hasActiveDng ? ['active_dng_resolution_required'] : [],
                ],
            );
        }

        return ['lines' => $lines, 'summary' => [
            'total_students' => count($lines),
            'total_amount' => array_sum(array_map(fn ($l) => $l->display['net'], $lines)),
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
