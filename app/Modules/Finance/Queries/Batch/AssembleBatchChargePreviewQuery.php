<?php

declare(strict_types=1);

namespace App\Modules\Finance\Queries\Batch;

use App\Modules\Finance\Queries\Egc\PreviewEgcChargeGenerationQuery;
use App\Modules\Finance\Queries\Major\PreviewMajorChargeGenerationQuery;
use App\Modules\Finance\Queries\Operations\PreviewChargeGenerationQuery;
use App\Modules\Finance\Support\Batch\BatchPreviewLine;
use App\Modules\Finance\Support\Batch\BatchPreviewLineHasher;

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
        private readonly PreviewChargeGenerationQuery $nonAcademicPreview,
    ) {}

    /**
     * @param  array<string, mixed>  $scope
     * @return array{lines: list<BatchPreviewLine>, summary: array<string, mixed>}
     */
    public function handle(string $feeCategory, int $semesterId, array $scope, ?int $campusId): array
    {
        return match ($feeCategory) {
            'major' => $this->fromMajor($semesterId, $scope, $campusId),
            'egc' => $this->fromEgc($semesterId, $scope, $campusId),
            'non_academic' => $this->fromNonAcademic($semesterId, $scope),
            default => ['lines' => [], 'summary' => []],
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
            'skip' => ($row['has_existing_charge'] ?? false) ? 'already_charged' : ((string) ($row['skip_reason'] ?? 'ineligible')),
            default => null,
        };

        $hashPayload = [
            'student_id' => (int) $row['id'],
            'semester_id' => $semesterId,
            'fee_category' => $feeCategory,
            'charge_type' => (string) ($row['charge_type'] ?? $feeCategory),
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
        ];

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
            $lines[] = self::mapChargeRow($row, 'egc', $semesterId, 'create');
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
        $result = $this->nonAcademicPreview->handle(array_merge($scope, ['semester_id' => $semesterId]));
        $lines = [];

        foreach (($result['students'] ?? []) as $row) {
            $bucket = match (true) {
                ! empty($row['warning']) => 'warning',
                ($row['has_existing_charge'] ?? false) => 'skip',
                default => 'create',
            };
            $lines[] = self::mapChargeRow($row, 'non_academic', $semesterId, $bucket);
        }

        return ['lines' => $lines, 'summary' => [
            'total_students' => $result['total_students'] ?? count($lines),
            'new_charges_count' => $result['new_charges_count'] ?? 0,
            'skip_count' => $result['skip_count'] ?? 0,
            'total_amount' => $result['total_amount'] ?? 0,
        ]];
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
            $normalized['estimated_amount'] = $row['estimated_amount'] ?? $row['net_amount'] ?? 0.0;
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