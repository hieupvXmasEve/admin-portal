<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\LegacyTranscriptOutcome;
use App\Shared\Contracts\Academic\LegacyTranscriptOutcomeReader;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class GetTranscriptDerivedEvidenceAuditQuery
{
    /** @var array<int, string> */
    private array $semesterOrder = [];

    public function __construct(
        private readonly LegacyTranscriptOutcomeReader $legacyOutcomes,
        private readonly AcademicPeriodReader $academicPeriods,
    ) {}

    /**
     * @param  array{student_id?: int, semester_id?: int, all?: bool}  $scope
     * @return array<string, mixed>
     */
    public function handle(array $scope): array
    {
        $this->semesterOrder = [];
        if (! ($scope['all'] ?? false) && ! isset($scope['student_id']) && ! isset($scope['semester_id'])) {
            throw new InvalidArgumentException('Specify --student-id, --semester-id, or --all.');
        }

        $sourceScope = array_filter([
            'student_id' => $scope['student_id'] ?? null,
            'semester_id' => $scope['semester_id'] ?? null,
        ], static fn (?int $value): bool => $value !== null);
        $requestedLegacy = collect($this->legacyOutcomes->finalizedOutcomes($sourceScope))
            ->map(fn (LegacyTranscriptOutcome $outcome): array => $this->legacyRow($outcome));
        $requestedTranscript = $this->transcriptRows($sourceScope);
        [$legacy, $transcript] = $this->historicalRows($scope, $requestedLegacy, $requestedTranscript);
        $legacyGpa = $this->reportedGpaRows($this->gpaRows($legacy), $scope['semester_id'] ?? null);
        $transcriptGpa = $this->reportedGpaRows($this->gpaRows($transcript), $scope['semester_id'] ?? null);
        $gpaMismatches = $this->differences($legacyGpa, $transcriptGpa, ['semester_gpa', 'cumulative_gpa']);
        $standingMismatches = $this->differences($legacyGpa, $transcriptGpa, ['academic_standing']);
        $reportedBestAttemptKeys = $this->reportedBestAttemptKeys($scope, $requestedLegacy, $requestedTranscript);
        $legacyBestAttempts = $this->reportedBestAttempts($this->bestAttempts($legacy), $reportedBestAttemptKeys);
        $transcriptBestAttempts = $this->reportedBestAttempts($this->bestAttempts($transcript), $reportedBestAttemptKeys);
        $bestAttemptMismatches = $this->differences(
            $legacyBestAttempts,
            $transcriptBestAttempts,
            ['course_offering_id', 'attempt_number', 'final_percentage', 'credit_points_earned'],
        );

        return [
            'read_only' => true,
            'scope' => [
                'student_id' => $scope['student_id'] ?? null,
                'semester_id' => $scope['semester_id'] ?? null,
                'all' => (bool) ($scope['all'] ?? false),
            ],
            'counts' => [
                'gpa_rows' => $legacyGpa->count(),
                'gpa_mismatches' => $gpaMismatches->count(),
                'standing_mismatches' => $standingMismatches->count(),
                'best_attempts' => $legacyBestAttempts->count(),
                'best_attempt_mismatches' => $bestAttemptMismatches->count(),
            ],
            'gpa_mismatches' => $gpaMismatches->values()->all(),
            'standing_mismatches' => $standingMismatches->values()->all(),
            'best_attempt_mismatches' => $bestAttemptMismatches->values()->all(),
        ];
    }

    /** @param Collection<int, array<string, bool|float|int|string|null>> $rows @return Collection<string, array<string, bool|float|int|string>> */
    private function gpaRows(Collection $rows): Collection
    {
        return $rows->groupBy('student_id')->flatMap(function (Collection $studentRows): Collection {
            $cumulativeQuality = 0.0;
            $cumulativeCredits = 0.0;

            return $studentRows
                ->groupBy('semester_id')
                ->sortBy(fn (Collection $semesterRows): string => $this->semesterOrder((int) $semesterRows->first()['semester_id']))
                ->map(function (Collection $semesterRows) use (&$cumulativeQuality, &$cumulativeCredits): array {
                    $gpaRows = $semesterRows->filter(static fn (array $row): bool => ! $row['excluded_from_gpa'] && $row['credit_points'] > 0);
                    $quality = (float) $gpaRows->sum(static fn (array $row): float => $row['final_percentage'] * $row['credit_points']);
                    $credits = (float) $gpaRows->sum('credit_points');
                    $cumulativeQuality += $quality;
                    $cumulativeCredits += $credits;
                    $semesterGpa = $credits === 0.0 ? 0.0 : round($quality / $credits, 3);
                    $cumulativeGpa = $cumulativeCredits === 0.0 ? 0.0 : round($cumulativeQuality / $cumulativeCredits, 3);
                    $studentId = (int) $semesterRows->first()['student_id'];
                    $semesterId = (int) $semesterRows->first()['semester_id'];

                    return [
                        'key' => $this->semesterKey($studentId, $semesterId),
                        'student_id' => $studentId,
                        'semester_id' => $semesterId,
                        'semester_gpa' => $semesterGpa,
                        'cumulative_gpa' => $cumulativeGpa,
                        'academic_standing' => $cumulativeGpa >= 50.0 ? 'normal' : 'warning',
                    ];
                })->keyBy('key');
        });
    }

    /** @param Collection<int, array<string, bool|float|int|string|null>> $rows @return Collection<string, array<string, bool|float|int|string>> */
    private function bestAttempts(Collection $rows): Collection
    {
        return $rows
            ->groupBy(fn (array $row): string => $this->bestAttemptKey((int) $row['student_id'], (int) $row['unit_id']))
            ->map(function (Collection $attempts, string $key): array {
                $best = $attempts->sort(static fn (array $left, array $right): int => [
                    $right['finalized_on'] ?? '',
                    $right['attempt_number'],
                ] <=> [
                    $left['finalized_on'] ?? '',
                    $left['attempt_number'],
                ])->first();

                return [
                    'key' => $key,
                    'student_id' => $best['student_id'],
                    'unit_id' => $best['unit_id'],
                    'course_offering_id' => $best['course_offering_id'],
                    'attempt_number' => $best['attempt_number'],
                    'final_percentage' => $best['final_percentage'],
                    'credit_points_earned' => $best['credit_points_earned'],
                ];
            });
    }

    /** @param Collection<string, array<string, bool|float|int|string>> $legacy @param Collection<string, array<string, bool|float|int|string>> $transcript @param list<string> $fields @return Collection<int, array<string, mixed>> */
    private function differences(Collection $legacy, Collection $transcript, array $fields): Collection
    {
        return $legacy->union($transcript)->keys()->unique()->sort()->map(function (string $key) use ($legacy, $transcript, $fields): ?array {
            $source = $legacy->get($key);
            $target = $transcript->get($key);
            $different = $source === null || $target === null;
            $differences = [];

            foreach ($fields as $field) {
                if ($this->normalize($source[$field] ?? null) !== $this->normalize($target[$field] ?? null)) {
                    $different = true;
                    $differences[] = $field;
                }
            }

            if (! $different) {
                return null;
            }

            return [
                'key' => $key,
                'student_id' => $source['student_id'] ?? $target['student_id'] ?? null,
                'semester_id' => $source['semester_id'] ?? $target['semester_id'] ?? null,
                'differences' => $differences === [] ? ['missing_source_or_target'] : $differences,
                'expected' => $source === null ? null : collect($fields)->mapWithKeys(fn (string $field): array => [$field => $source[$field] ?? null])->all(),
                'actual' => $target === null ? null : collect($fields)->mapWithKeys(fn (string $field): array => [$field => $target[$field] ?? null])->all(),
            ];
        })->filter()->values();
    }

    /** @return array<string, bool|float|int|null> */
    private function legacyRow(LegacyTranscriptOutcome $outcome): array
    {
        return [
            'student_id' => $outcome->studentId,
            'course_offering_id' => $outcome->courseOfferingId,
            'semester_id' => $outcome->semesterId,
            'unit_id' => $outcome->unitId,
            'attempt_number' => $outcome->attemptNumber,
            'final_percentage' => $outcome->finalPercentage,
            'credit_points' => $outcome->creditPoints,
            'credit_points_earned' => $outcome->creditPointsEarned,
            'is_passed' => $outcome->isPassed,
            'excluded_from_gpa' => $outcome->excludedFromGpa,
            'finalized_on' => $outcome->finalizedOn,
        ];
    }

    /** @return array<string, bool|float|int|string|null> */
    private function transcriptRow(TranscriptEntry $entry): array
    {
        return [
            'student_id' => (int) $entry->student_id,
            'course_offering_id' => (int) $entry->course_offering_id,
            'semester_id' => (int) $entry->semester_id,
            'unit_id' => (int) $entry->unit_id,
            'attempt_number' => (int) $entry->attempt_number,
            'final_percentage' => (float) $entry->final_percentage,
            'credit_points' => (float) $entry->credit_points,
            'credit_points_earned' => (float) $entry->credit_points_earned,
            'is_passed' => (bool) $entry->is_passed,
            'excluded_from_gpa' => (bool) $entry->excluded_from_gpa,
            'finalized_on' => $entry->finalizedOn(),
        ];
    }

    /** @param array{student_id?: int, semester_id?: int, all?: bool} $scope @param Collection<int, array<string, bool|float|int|string|null>> $requestedLegacy @param Collection<int, array<string, bool|float|int|string|null>> $requestedTranscript @return array{Collection<int, array<string, bool|float|int|string|null>>, Collection<int, array<string, bool|float|int|string|null>>} */
    private function historicalRows(array $scope, Collection $requestedLegacy, Collection $requestedTranscript): array
    {
        if (! isset($scope['semester_id'])) {
            return [$requestedLegacy, $requestedTranscript];
        }

        if (isset($scope['student_id'])) {
            return [
                collect($this->legacyOutcomes->finalizedOutcomes(['student_id' => $scope['student_id']]))
                    ->map(fn (LegacyTranscriptOutcome $outcome): array => $this->legacyRow($outcome)),
                $this->transcriptRows(['student_id' => $scope['student_id']]),
            ];
        }

        $studentIds = $requestedLegacy->pluck('student_id')
            ->merge($requestedTranscript->pluck('student_id'))
            ->filter(static fn (mixed $studentId): bool => is_int($studentId))
            ->unique()
            ->sort()
            ->values()
            ->all();
        if ($studentIds === []) {
            return [collect(), collect()];
        }

        return [
            collect($this->legacyOutcomes->finalizedOutcomes(['student_ids' => $studentIds]))
                ->map(fn (LegacyTranscriptOutcome $outcome): array => $this->legacyRow($outcome)),
            $this->transcriptRows(['student_ids' => $studentIds]),
        ];
    }

    /** @param array{student_id?: int, student_ids?: list<int>, semester_id?: int} $scope @return Collection<int, array<string, bool|float|int|string|null>> */
    private function transcriptRows(array $scope): Collection
    {
        return TranscriptEntry::query()
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['student_ids']), fn ($query) => $query->whereIn('student_id', $scope['student_ids']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->get()
            ->map(fn (TranscriptEntry $entry): array => $this->transcriptRow($entry));
    }

    /** @param Collection<string, array<string, bool|float|int|string>> $rows @return Collection<string, array<string, bool|float|int|string>> */
    private function reportedGpaRows(Collection $rows, ?int $semesterId): Collection
    {
        return $semesterId === null
            ? $rows
            : $rows->where('semester_id', $semesterId);
    }

    /** @param array{student_id?: int, semester_id?: int, all?: bool} $scope @param Collection<int, array<string, bool|float|int|string|null>> $requestedLegacy @param Collection<int, array<string, bool|float|int|string|null>> $requestedTranscript @return list<string>|null */
    private function reportedBestAttemptKeys(array $scope, Collection $requestedLegacy, Collection $requestedTranscript): ?array
    {
        if (! isset($scope['semester_id'])) {
            return null;
        }

        return $requestedLegacy->merge($requestedTranscript)
            ->map(fn (array $row): string => $this->bestAttemptKey((int) $row['student_id'], (int) $row['unit_id']))
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    /** @param Collection<string, array<string, bool|float|int|string>> $rows @param list<string>|null $keys @return Collection<string, array<string, bool|float|int|string>> */
    private function reportedBestAttempts(Collection $rows, ?array $keys): Collection
    {
        return $keys === null ? $rows : $rows->only($keys);
    }

    private function semesterKey(int $studentId, int $semesterId): string
    {
        return implode(':', ['student', $studentId, 'semester', $semesterId]);
    }

    private function bestAttemptKey(int $studentId, int $unitId): string
    {
        return implode(':', ['student', $studentId, 'unit', $unitId]);
    }

    private function semesterOrder(int $semesterId): string
    {
        if (isset($this->semesterOrder[$semesterId])) {
            return $this->semesterOrder[$semesterId];
        }

        $period = $this->academicPeriods->find($semesterId);

        return $this->semesterOrder[$semesterId] = $period?->start_date?->format('Y-m-d')
            ?? sprintf('missing:%010d', $semesterId);
    }

    private function normalize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return number_format((float) $value, 3, '.', '');
        }

        return $value === null ? 'null' : (string) $value;
    }
}
