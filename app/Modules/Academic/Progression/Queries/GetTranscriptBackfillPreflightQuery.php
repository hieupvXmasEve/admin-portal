<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\DTO\LegacyTranscriptOutcome;
use App\Shared\Contracts\Academic\LegacyTranscriptOutcomeReader;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class GetTranscriptBackfillPreflightQuery
{
    public function __construct(private readonly LegacyTranscriptOutcomeReader $legacyOutcomes) {}

    /**
     * @param  array{student_id?: int, semester_id?: int, all?: bool}  $scope
     * @return array<string, mixed>
     */
    public function handle(array $scope): array
    {
        if (! ($scope['all'] ?? false) && ! isset($scope['student_id']) && ! isset($scope['semester_id'])) {
            throw new InvalidArgumentException('Specify --student-id, --semester-id, or --all.');
        }

        $sourceScope = array_filter([
            'student_id' => $scope['student_id'] ?? null,
            'semester_id' => $scope['semester_id'] ?? null,
        ], static fn (?int $value): bool => $value !== null);
        $sources = collect($this->legacyOutcomes->finalizedOutcomes($sourceScope));
        $targets = $this->targets($sourceScope);
        $targetByCourseResult = $targets->keyBy('course_result_id');
        $sourceByCourseResult = $sources->keyBy('courseResultId');
        $duplicateCourseResultIds = $this->duplicateCourseResultIds($sources);
        $ready = collect();
        $matching = collect();
        $conflicts = collect();
        $invalid = collect();
        $ambiguous = collect();
        $datePrecisionWarnings = collect();

        foreach ($sources as $source) {
            $row = $this->row($source);
            $invalidReasons = $this->invalidReasons($source);
            if ($invalidReasons !== []) {
                $invalid->push([...$row, 'reasons' => $invalidReasons]);

                continue;
            }

            if (isset($duplicateCourseResultIds[$source->courseResultId])) {
                $conflicts->push([...$row, 'reasons' => ['duplicate_business_attempt_candidate']]);

                continue;
            }

            $target = $targetByCourseResult->get($source->courseResultId);
            if ($target === null) {
                if ($source->finalizedOn !== null) {
                    $ambiguous->push([...$row, 'reasons' => ['legacy_finalization_is_date_only']]);

                    continue;
                }

                $ready->push($row);

                continue;
            }

            $differences = $this->differences($source, $target);
            if (($source->finalizedOn === null && $target->finalized_at !== null)
                || ($source->finalizedOn !== null && $target->finalized_at?->toDateString() !== $source->finalizedOn)) {
                $differences[] = 'mismatch_finalization_date';
            }
            if ($differences === []) {
                $matching->push($row);
                if ($source->finalizedOn !== null) {
                    $datePrecisionWarnings->push([...$row, 'reasons' => ['legacy_finalization_is_date_only']]);
                }

                continue;
            }

            $conflicts->push([...$row, 'reasons' => $differences]);
        }

        $unmatchedTargets = $targets
            ->reject(fn (TranscriptEntry $target): bool => $sourceByCourseResult->has((int) $target->course_result_id))
            ->map(fn (TranscriptEntry $target): array => [
                'course_result_id' => (int) $target->course_result_id,
                'transcript_entry_id' => (int) $target->id,
                'business_key' => $this->targetBusinessKey($target),
                'reasons' => ['no_live_final_legacy_source'],
            ])->values();

        return [
            'read_only' => true,
            'scope' => [
                'student_id' => $scope['student_id'] ?? null,
                'semester_id' => $scope['semester_id'] ?? null,
                'all' => (bool) ($scope['all'] ?? false),
                'source_table' => 'academic_records',
                'target_table' => 'transcript_entries',
                'stable_identifier' => 'academic_records.id -> transcript_entries.course_result_id',
            ],
            'counts' => [
                'source_final_outcomes' => $sources->count(),
                'target_transcript_entries' => $targets->count(),
                'ready_to_backfill' => $ready->count(),
                'already_matching' => $matching->count(),
                'conflicts' => $conflicts->count(),
                'invalid_sources' => $invalid->count(),
                'ambiguous_sources' => $ambiguous->count(),
                'date_precision_warnings' => $datePrecisionWarnings->count(),
                'unmatched_targets' => $unmatchedTargets->count(),
            ],
            'ready_to_backfill' => $ready->values()->all(),
            'already_matching' => $matching->values()->all(),
            'conflicts' => $conflicts->values()->all(),
            'invalid_sources' => $invalid->values()->all(),
            'ambiguous_sources' => $ambiguous->values()->all(),
            'date_precision_warnings' => $datePrecisionWarnings->values()->all(),
            'unmatched_targets' => $unmatchedTargets->all(),
        ];
    }

    /** @param array{student_id?: int, semester_id?: int} $scope @return Collection<int, TranscriptEntry> */
    private function targets(array $scope): Collection
    {
        return TranscriptEntry::query()
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->orderBy('id')
            ->get();
    }

    /** @param Collection<int, LegacyTranscriptOutcome> $sources @return array<int, true> */
    private function duplicateCourseResultIds(Collection $sources): array
    {
        return $sources
            ->groupBy(fn (LegacyTranscriptOutcome $source): string => $this->businessKey($source))
            ->filter(fn (Collection $candidates): bool => $candidates->count() > 1)
            ->flatten()
            ->mapWithKeys(fn (LegacyTranscriptOutcome $source): array => [$source->courseResultId => true])
            ->all();
    }

    /** @return list<string> */
    private function invalidReasons(LegacyTranscriptOutcome $source): array
    {
        $reasons = [];
        foreach ([
            'student_id' => $source->studentId,
            'course_offering_id' => $source->courseOfferingId,
            'semester_id' => $source->semesterId,
            'unit_id' => $source->unitId,
            'program_id' => $source->programId,
            'campus_id' => $source->campusId,
            'attempt_number' => $source->attemptNumber,
            'final_percentage' => $source->finalPercentage,
            'final_letter_grade' => $source->finalLetterGrade,
            'credit_points' => $source->creditPoints,
            'credit_points_earned' => $source->creditPointsEarned,
            'quality_points' => $source->qualityPoints,
            'is_passed' => $source->isPassed,
            'excluded_from_gpa' => $source->excludedFromGpa,
            'affects_academic_standing' => $source->affectsAcademicStanding,
            'affects_graduation_requirement' => $source->affectsGraduationRequirement,
            'satisfies_prerequisite' => $source->satisfiesPrerequisite,
        ] as $field => $value) {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $reasons[] = 'missing_'.$field;
            }
        }

        if ($source->attemptNumber !== null && $source->attemptNumber < 1) {
            $reasons[] = 'invalid_attempt_number';
        }

        return $reasons;
    }

    /** @return list<string> */
    private function differences(LegacyTranscriptOutcome $source, TranscriptEntry $target): array
    {
        $differences = [];
        foreach ($this->expected($source) as $field => $expected) {
            if ($this->normalize($expected) !== $this->normalize($target->getAttribute($field))) {
                $differences[] = 'mismatch_'.$field;
            }
        }

        return $differences;
    }

    /** @return array<string, bool|float|int|string|null> */
    private function expected(LegacyTranscriptOutcome $source): array
    {
        return [
            'student_id' => $source->studentId,
            'course_offering_id' => $source->courseOfferingId,
            'semester_id' => $source->semesterId,
            'unit_id' => $source->unitId,
            'program_id' => $source->programId,
            'campus_id' => $source->campusId,
            'attempt_number' => $source->attemptNumber,
            'final_percentage' => $source->finalPercentage,
            'final_letter_grade' => $source->finalLetterGrade,
            'credit_points' => $source->creditPoints,
            'credit_points_earned' => $source->creditPointsEarned,
            'quality_points' => $source->qualityPoints,
            'is_passed' => $source->isPassed,
            'excluded_from_gpa' => $source->excludedFromGpa,
            'affects_academic_standing' => $source->affectsAcademicStanding,
            'affects_graduation_requirement' => $source->affectsGraduationRequirement,
            'satisfies_prerequisite' => $source->satisfiesPrerequisite,
        ];
    }

    /** @return array{course_result_id: int, business_key: string} */
    private function row(LegacyTranscriptOutcome $source): array
    {
        return [
            'course_result_id' => $source->courseResultId,
            'business_key' => $this->businessKey($source),
        ];
    }

    private function businessKey(LegacyTranscriptOutcome $source): string
    {
        return implode(':', [
            'student', $source->studentId ?? 'missing',
            'semester', $source->semesterId ?? 'missing',
            'unit', $source->unitId ?? 'missing',
            'attempt', $source->attemptNumber ?? 'missing',
        ]);
    }

    private function targetBusinessKey(TranscriptEntry $target): string
    {
        return implode(':', [
            'student', $target->student_id,
            'semester', $target->semester_id,
            'unit', $target->unit_id,
            'attempt', $target->attempt_number,
        ]);
    }

    private function normalize(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_float($value) || is_int($value)) {
            return number_format((float) $value, 2, '.', '');
        }

        return $value === null ? 'null' : (string) $value;
    }
}
