<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Batch;

use App\Models\Student;
use App\Modules\Finance\Models\FinanceCharge;
use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use App\Modules\Finance\Queries\Major\PreviewMajorChargeGenerationQuery;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use App\Modules\Finance\Support\Batch\BatchPreviewLineHasher;
use App\Modules\Finance\Support\BillingScopeHelper;
use App\Modules\Finance\Support\StudentChargeTimingResolver;

/**
 * Step ② for sinh phí: delegates to the existing Preview*Query per fee category and
 * normalizes every row into the canonical BatchPreviewLine. No new money math —
 * the amounts come straight from the shared resolvers inside those queries.
 */
class AssembleBatchChargePreviewQuery
{
    public function __construct(
        private readonly PreviewMajorChargeGenerationQuery $majorPreview,
        private readonly PreviewEgcChargeGenerationQuery $egcPreview,
    ) {}

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    public function handle(string $feeCategory, int $semesterId, array $scope, ?int $campusId): array
    {
        $scope = $this->normalizeScope($feeCategory, $semesterId, $scope);

        return match ($feeCategory) {
            'major' => $this->fromMajor($semesterId, $scope, $campusId),
            'egc' => $this->fromEgc($semesterId, $scope, $campusId),
            'non_academic' => $this->fromNonAcademic($semesterId, $scope),
            default => ['lines' => [], 'summary' => []],
        };
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array<string, mixed>
     */
    public function normalizeScope(string $feeCategory, int $semesterId, array $scope): array
    {
        return match ($feeCategory) {
            'non_academic' => $this->normalizeNonAcademicScope($scope),
            default => [
                'filters' => $this->normalizeFilters($scope['filters'] ?? []),
            ],
        };
    }

    /**
     * Pure mapping of one preview row into a canonical line. (Unit-tested.)
     *
     * @param  array<string, mixed>  $row
     * @param  'create'|'update'|'skip'|'warning'  $bucket
     */
    public static function mapChargeRow(array $row, string $feeCategory, int $semesterId, string $bucket): BatchPreviewLine
    {
        $row = self::normalizeRow($row, $feeCategory);

        $warningCodes = isset($row['warning']) && $row['warning'] !== null ? [(string) $row['warning']] : [];
        $reason = match ($bucket) {
            'warning' => (string) ($row['warning'] ?? ''),
            'update' => (string) ($row['update_reason'] ?? $row['eligibility_reason'] ?? ''),
            'skip' => ($row['has_existing_charge'] ?? false) ? 'already_charged' : ((string) ($row['skip_reason'] ?? 'ineligible')),
            default => null,
        };
        $chargeableLevels = collect($row['chargeable_levels'] ?? []);

        $hashPayload = [
            'student_id' => (int) $row['id'],
            'semester_id' => $semesterId,
            'fee_category' => $feeCategory,
            'charge_type' => (string) ($row['charge_type'] ?? $feeCategory),
            'generation_mode' => (string) ($row['generation_mode'] ?? 'normal'),
            'eligibility_reason' => $row['eligibility_reason'] ?? $row['skip_reason'] ?? null,
            'source_type' => $row['source_type'] ?? null,
            'source_id' => isset($row['source_id']) ? (int) $row['source_id'] : null,
            'fee_config_fingerprint' => BatchPreviewLineHasher::feeConfigFingerprint(
                isset($row['fee_plan_id']) ? (int) $row['fee_plan_id'] : null,
                $row['fee_plan_updated_at'] ?? null,
                isset($row['fee_term_id']) ? (int) $row['fee_term_id'] : null,
                $row['fee_term_updated_at'] ?? null,
            ),
            'scholarship_id' => isset($row['scholarship_id']) ? (int) $row['scholarship_id'] : null,
            'voucher_id' => isset($row['voucher_id']) ? (int) $row['voucher_id'] : null,
            'gross' => (float) ($row['gross_amount'] ?? $row['estimated_amount'] ?? 0),
            'discount' => (float) ($row['discount_amount'] ?? 0),
            'net' => (float) ($row['estimated_amount'] ?? 0),
            'warning_codes' => $warningCodes,
            'max_chargeable_blocks' => isset($row['max_chargeable_blocks']) ? (int) $row['max_chargeable_blocks'] : null,
            'chargeable_levels' => $chargeableLevels
                ->map(fn (mixed $level): ?int => is_array($level) && isset($level['level_number']) ? (int) $level['level_number'] : null)
                ->filter(fn (?int $level): bool => $level !== null)
                ->values()
                ->all(),
            'egc_block_signature' => collect($row['existing_egc_blocks'] ?? [])
                ->map(fn (mixed $block): ?array => is_array($block) ? [
                    'block_number' => (int) ($block['block_number'] ?? 0),
                    'level_number' => (int) ($block['level_number'] ?? 0),
                    'finance_charge_id' => isset($block['finance_charge_id']) ? (int) $block['finance_charge_id'] : null,
                ] : null)
                ->filter(fn (?array $block): bool => $block !== null)
                ->values()
                ->all(),
        ];

        $blockAmounts = $chargeableLevels
            ->map(fn (mixed $level): float => is_array($level) ? (float) ($level['amount'] ?? 0) : 0.0)
            ->filter(fn (float $amount): bool => $amount > 0)
            ->values()
            ->all();

        return new BatchPreviewLine(
            key: sprintf('charge:%s:student:%d:semester:%d', $feeCategory, (int) $row['id'], $semesterId),
            hashPayload: $hashPayload,
            display: [
                'student_id' => (string) ($row['student_code'] ?? $row['student_id'] ?? ''),
                'label' => (string) ($row['full_name'] ?? ''),
                'diff' => $bucket,
                'gross' => $hashPayload['gross'],
                'discount' => $hashPayload['discount'],
                'net' => $hashPayload['net'],
                'reason' => $reason,
                'warning_codes' => $warningCodes,
                'block_count' => isset($row['max_chargeable_blocks']) ? (int) $row['max_chargeable_blocks'] : null,
                'block_amounts' => $blockAmounts,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    private function fromMajor(int $semesterId, array $scope, ?int $campusId): array
    {
        $result = $this->majorPreview->handle($semesterId, $scope['filters'] ?? [], $campusId);
        $lines = [];

        foreach ($this->collection($result['eligible_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'major', $semesterId, 'create');
        }
        foreach (($result['warning_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'major', $semesterId, 'warning');
        }
        foreach (($result['ineligible_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'major', $semesterId, 'skip');
        }

        return ['lines' => $lines, 'summary' => $result['summary'] ?? []];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    private function fromEgc(int $semesterId, array $scope, ?int $campusId): array
    {
        $result = $this->egcPreview->handle($semesterId, $scope['filters'] ?? [], $campusId);
        $lines = [];

        foreach ($this->collection($result['eligible_students'] ?? []) as $row) {
            $bucket = ($row['generation_mode'] ?? null) === 'reissue' ? 'update' : 'create';
            $lines[] = self::mapChargeRow($row, 'egc', $semesterId, $bucket);
        }
        foreach (($result['warning_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'egc', $semesterId, 'warning');
        }
        foreach (($result['ineligible_students'] ?? []) as $row) {
            $lines[] = self::mapChargeRow($row, 'egc', $semesterId, 'skip');
        }

        return ['lines' => $lines, 'summary' => $result['summary'] ?? []];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    private function fromNonAcademic(int $semesterId, array $scope): array
    {
        $feeType = (string) ($scope['fee_type'] ?? '');
        $amount = (float) ($scope['amount'] ?? 0);
        $filters = $this->normalizeFilters($scope['filters'] ?? []);

        $query = BillingScopeHelper::getEligibleStudentsQuery($semesterId, 'all_eligible');

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('students.student_id', 'like', "%{$search}%")
                    ->orWhere('students.full_name', 'like', "%{$search}%");
            });
        }

        $ignoredStudentIds = $this->ignoredStudentIds($filters['ignore_student_ids'] ?? null);
        if ($ignoredStudentIds !== []) {
            $query->whereNotIn('students.id', $ignoredStudentIds);
        }

        /** @var StudentChargeTimingResolver $timingResolver */
        $timingResolver = app(StudentChargeTimingResolver::class);
        $students = $query->limit(500)->get()
            ->filter(fn (Student $student): bool => $timingResolver->shouldIncludeStudentForChargeGeneration($student, $semesterId, [$feeType]))
            ->values();

        $lines = [];
        $newChargesCount = 0;
        $skipCount = 0;
        $totalAmount = 0.0;

        foreach ($students as $student) {
            $hasExistingCharge = FinanceCharge::query()
                ->where('student_id', $student->id)
                ->where('semester_id', $semesterId)
                ->where('charge_type', $feeType)
                ->where('status', FinanceCharge::STATUS_ACTIVE)
                ->exists();

            if ($hasExistingCharge) {
                $skipCount++;
            } else {
                $newChargesCount++;
                $totalAmount += $amount;
            }

            $bucket = $hasExistingCharge ? 'skip' : 'create';
            $row = [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'full_name' => $student->full_name,
                'charge_type' => $feeType,
                'has_existing_charge' => $hasExistingCharge,
                'estimated_amount' => $amount,
                'gross_amount' => $amount,
                'discount_amount' => 0.0,
                'skip_reason' => $hasExistingCharge ? 'already_charged' : null,
            ];

            $lines[] = self::mapChargeRow($row, 'non_academic', $semesterId, $bucket);
        }

        return ['lines' => $lines, 'summary' => [
            'total_students' => count($lines),
            'new_charges_count' => $newChargesCount,
            'skip_count' => $skipCount,
            'total_amount' => $totalAmount,
        ]];
    }

    /**
     * @param  array<string, mixed>  $scope
     * @return array{filters: array<string, mixed>, fee_type: string, amount: float, note: string}
     */
    private function normalizeNonAcademicScope(array $scope): array
    {
        return [
            'filters' => $this->normalizeFilters($scope['filters'] ?? []),
            'fee_type' => (string) ($scope['fee_type'] ?? ''),
            'amount' => (float) ($scope['amount'] ?? 0),
            'note' => mb_substr(trim((string) ($scope['note'] ?? '')), 0, 255),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFilters(mixed $filters): array
    {
        return is_array($filters) ? $filters : [];
    }

    /**
     * @return int[]
     */
    private function ignoredStudentIds(mixed $raw): array
    {
        if (is_array($raw)) {
            return array_values(array_filter(array_map('intval', $raw)));
        }

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        return collect(explode(',', $raw))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }

    /**
     * Normalize preview query rows to the keys mapChargeRow expects.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private static function normalizeRow(array $row, string $feeCategory): array
    {
        $normalized = $row;

        $normalized['id'] = $row['id'] ?? $row['student_id'] ?? 0;
        $normalized['full_name'] = $row['full_name'] ?? $row['student_name'] ?? '';
        $normalized['student_code'] = $row['student_code'] ?? (is_string($row['student_id'] ?? null) ? $row['student_id'] : '');

        if ($feeCategory === 'major' || $feeCategory === 'egc') {
            $chargeableLevelAmount = collect($row['chargeable_levels'] ?? [])
                ->sum(fn (mixed $level): float => is_array($level) ? (float) ($level['amount'] ?? 0) : 0.0);
            $normalized['estimated_amount'] = $row['estimated_amount'] ?? $row['net_amount'] ?? $chargeableLevelAmount;
            $normalized['gross_amount'] = $row['gross_amount'] ?? $row['amount'] ?? $normalized['estimated_amount'];
            $normalized['discount_amount'] = $row['discount_amount']
                ?? ((float) ($row['scholarship_amount'] ?? 0) + (float) ($row['voucher_amount'] ?? 0));
            $normalized['warning'] = $row['warning'] ?? $row['eligibility_reason'] ?? null;
            $normalized['has_existing_charge'] = $row['has_existing_charge']
                ?? (($row['eligibility_reason'] ?? null) === 'already_charged');
            $normalized['skip_reason'] = $row['skip_reason'] ?? $row['eligibility_reason'] ?? 'ineligible';
        }

        return $normalized;
    }

    /**
     * Normalize a paginator or array into an iterable of row arrays.
     *
     * @param  mixed  $value
     * @return iterable<array<string, mixed>>
     */
    private function collection($value): iterable
    {
        if (is_array($value)) {
            return $value;
        }

        return method_exists($value, 'items') ? $value->items() : (array) $value;
    }
}
