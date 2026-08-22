<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Enums\AcademicProgressionEventType;
use App\Enums\StudentActionType;
use App\Modules\Academic\Progression\Models\TranscriptEntry;
use App\Shared\Contracts\Academic\AcademicPeriodReader;
use App\Shared\Contracts\Academic\DTO\StudentHubCourseOutcomeEvidence;
use App\Shared\Contracts\Academic\StudentHubCourseOutcomeEvidenceReader;
use App\Shared\Support\Academic\GpaValueComparator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GetAcademicProgressionReconciliationQuery
{
    /** @var array<int, string> */
    private array $semesterOrder = [];

    public function __construct(
        private readonly GetTranscriptBackfillPreflightQuery $preflight,
        private readonly GetTranscriptDerivedEvidenceAuditQuery $derivedAudit,
        private readonly AcademicPeriodReader $academicPeriods,
        private readonly GetStudentGraduationProgressQuery $graduationProgress,
        private readonly StudentHubCourseOutcomeEvidenceReader $studentHubOutcomes,
    ) {}

    /**
     * @param  array{student_id?: int, semester_id?: int, all?: bool}  $scope
     * @return array<string, mixed>
     */
    public function handle(array $scope): array
    {
        $this->assertScope($scope);
        $this->semesterOrder = [];

        $sourceScope = array_filter([
            'student_id' => $scope['student_id'] ?? null,
            'semester_id' => $scope['semester_id'] ?? null,
        ], static fn (?int $value): bool => $value !== null);

        $preflight = $this->preflight->handle($scope);
        $derived = $this->derivedAudit->handle($scope);
        $gpa = $this->reconcilePersistedGpa($sourceScope);
        $lifecycle = $this->reconcileLifecycle($sourceScope);
        $graduation = $this->evaluateGraduation($sourceScope);
        $studentHub = $this->reconcileStudentHubEvidence($sourceScope);
        $consumerInventory = $this->consumerInventory();

        $exceptions = collect([
            ...$this->transcriptExceptions($preflight),
            ...$this->derivedExceptions($derived),
            ...$gpa['exceptions'],
            ...$lifecycle['exceptions'],
            ...$graduation['exceptions'],
            ...$studentHub['exceptions'],
        ])->values()->all();

        return [
            'read_only' => true,
            'scope' => [
                'student_id' => $scope['student_id'] ?? null,
                'semester_id' => $scope['semester_id'] ?? null,
                'all' => (bool) ($scope['all'] ?? false),
            ],
            'environment' => [
                'timezone' => (string) config('app.timezone'),
                'source_tables' => ['academic_records', 'gpa_calculations', 'student_action_logs', 'academic_progression_events', 'egc_blocks', 'student_decisions', 'student_decision_student', 'course_registrations', 'students', 'program_enrollments'],
                'target_tables' => ['transcript_entries', 'gpa_calculations', 'program_enrollments'],
            ],
            'stable_identifiers' => [
                'transcript' => 'academic_records.id -> transcript_entries.course_result_id',
                'transcript_business_key' => 'student_id + semester_id + unit_id + attempt_number',
                'gpa' => 'student_id + semester_id',
                'best_attempt' => 'student_id + unit_id',
                'lifecycle_action' => 'action-{student_action_logs.id}',
                'progression_event' => 'progression-{academic_progression_events.id}',
                'decision_roster' => 'student_decision_student.student_decision_id + student_id',
            ],
            'counts' => [
                'transcript_exceptions' => count($this->transcriptExceptions($preflight)),
                'derived_exceptions' => count($this->derivedExceptions($derived)),
                'persisted_gpa_exceptions' => count($gpa['exceptions']),
                'lifecycle_exceptions' => count($lifecycle['exceptions']),
                'graduation_exceptions' => count($graduation['exceptions']),
                'student_hub_exceptions' => count($studentHub['exceptions']),
                'exceptions' => count($exceptions),
                'supported_consumers' => count($consumerInventory['supported_runtime_consumers']),
            ],
            'transcript' => [
                'counts' => $preflight['counts'],
                'derived_counts' => $derived['counts'],
            ],
            'persisted_gpa' => [
                'counts' => $gpa['counts'],
                'exceptions' => $gpa['exceptions'],
                'compatibility_conditions' => $gpa['compatibility_conditions'],
            ],
            'lifecycle_and_decisions' => [
                'counts' => $lifecycle['counts'],
                'exceptions' => $lifecycle['exceptions'],
            ],
            'graduation_and_egc' => $graduation,
            'student_hub_and_portals' => [
                'final_legacy_outcomes_without_transcript' => $this->legacyOutcomesWithoutTranscript($sourceScope),
                'counts' => $studentHub['counts'],
                'exceptions' => $studentHub['exceptions'],
                'legacy_only_compatibility_fields' => $studentHub['legacy_only_compatibility_fields'],
                'comparison_status' => 'normalized_consumer_evidence_compared',
                'note' => 'The active Student Hub evidence projection is field-compared against transcript entries, but remains a supported runtime consumer and is not retirement-eligible in this checkpoint.',
            ],
            'consumer_inventory' => $consumerInventory,
            'exceptions' => $exceptions,
            'reconciliation_passed' => $exceptions === [],
            'retirement_eligible' => $exceptions === [] && $consumerInventory['supported_runtime_consumers'] === [],
            'retirement_approval' => [
                'required' => true,
                'recorded' => false,
                'reason' => $consumerInventory['supported_runtime_consumers'] === []
                    ? 'A separate named human approval is still required.'
                    : 'Supported runtime consumers remain; projection retirement is not eligible.',
            ],
        ];
    }

    /** @param array{student_id?: int, semester_id?: int, all?: bool} $scope */
    private function assertScope(array $scope): void
    {
        if (! ($scope['all'] ?? false) && ! isset($scope['student_id']) && ! isset($scope['semester_id'])) {
            throw new InvalidArgumentException('Specify --student-id, --semester-id, or --all.');
        }
        if (($scope['all'] ?? false) && (isset($scope['student_id']) || isset($scope['semester_id']))) {
            throw new InvalidArgumentException('--all cannot be combined with --student-id or --semester-id.');
        }
    }

    /** @param array<string, mixed> $report @return list<array<string, mixed>> */
    private function transcriptExceptions(array $report): array
    {
        return collect([
            ...$report['conflicts'],
            ...$report['invalid_sources'],
            ...$report['ambiguous_sources'],
            ...$report['unmatched_targets'],
            ...$report['ready_to_backfill'],
        ])->map(fn (array $row): array => [
            'invariant_code' => 'TRN-001',
            'stable_key' => $row['business_key'] ?? 'course-result:'.$row['course_result_id'],
            'source_table' => 'academic_records',
            'target_table' => 'transcript_entries',
            'source_ids' => isset($row['course_result_id']) ? [(int) $row['course_result_id']] : [],
            'target_ids' => isset($row['transcript_entry_id']) ? [(int) $row['transcript_entry_id']] : [],
            'severity' => 'error',
            'reason' => implode(',', $row['reasons'] ?? ['unexplained_transcript_difference']),
        ])->values()->all();
    }

    /** @param array<string, mixed> $report @return list<array<string, mixed>> */
    private function derivedExceptions(array $report): array
    {
        return collect([
            ...$report['gpa_mismatches'],
            ...$report['standing_mismatches'],
            ...$report['best_attempt_mismatches'],
        ])->map(function (array $row): array {
            [$sourceIds, $targetIds] = $this->derivedEvidenceIds((string) ($row['key'] ?? ''));

            return [
                'invariant_code' => str_contains(implode(',', $row['differences'] ?? []), 'academic_standing') ? 'STN-001' : 'GPA-001',
                'stable_key' => $row['key'] ?? 'unknown',
                'source_table' => 'academic_records',
                'target_table' => 'transcript_entries',
                'source_ids' => $sourceIds,
                'target_ids' => $targetIds,
                'field' => implode(',', $row['differences'] ?? []),
                'expected' => $row['expected'] ?? null,
                'actual' => $row['actual'] ?? null,
                'severity' => 'error',
                'reason' => 'legacy_and_transcript_derived_values_differ',
            ];
        })->values()->all();
    }

    /** @param array{student_id?: int, semester_id?: int} $scope @return array<string, mixed> */
    private function reconcilePersistedGpa(array $scope): array
    {
        $allExpected = $this->expectedGpaRows($scope);
        $expected = isset($scope['semester_id'])
            ? collect($allExpected)->filter(fn (array $row, string $key): bool => str_ends_with($key, ':semester:'.$scope['semester_id']))->all()
            : $allExpected;
        $actualRows = DB::table('gpa_calculations')
            ->where('is_finalized', true)
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->get();
        $actual = $actualRows
            ->keyBy(fn (object $row): string => $this->gpaKey((int) $row->student_id, (int) $row->semester_id));
        $exceptions = [];

        foreach ($actualRows->groupBy(fn (object $row): string => $this->gpaKey((int) $row->student_id, (int) $row->semester_id))->filter(static fn (Collection $rows): bool => $rows->count() > 1) as $key => $rows) {
            $exceptions[] = $this->gpaException(
                $key,
                'duplicate_finalized_calculations_for_business_key',
                null,
                null,
                null,
                null,
                $rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            );
        }

        foreach ($expected as $key => $row) {
            $calculation = $actual->get($key);
            if ($calculation === null) {
                $exceptions[] = $this->gpaException($key, 'missing_persisted_calculation', null, $row);

                continue;
            }

            foreach ([
                'semester_gpa', 'cumulative_gpa', 'semester_quality_points', 'cumulative_quality_points',
                'semester_credit_points', 'cumulative_credit_points', 'semester_credit_points_earned',
                'cumulative_credit_points_earned', 'academic_standing', 'program_id',
            ] as $field) {
                $actualValue = $calculation->{$field};
                if ($this->normalize($actualValue) !== $this->normalize($row[$field])) {
                    $exceptions[] = $this->gpaException($key, 'persisted_value_differs_from_transcript_derived_value', $field, $row, $actualValue, (int) $calculation->id);
                }
            }

            if ($calculation->finalized_at === null) {
                $exceptions[] = [
                    'invariant_code' => 'GPA-003',
                    'stable_key' => $key,
                    'source_table' => 'gpa_calculations',
                    'target_table' => 'gpa_calculations',
                    'source_ids' => [],
                    'target_ids' => [(int) $calculation->id],
                    'field' => 'finalized_at',
                    'expected' => 'non-null for finalized calculation',
                    'actual' => null,
                    'severity' => 'error',
                    'reason' => 'finalized_calculation_missing_finalized_at',
                ];
            }
        }

        foreach ($actual->keys()->diff(array_keys($expected)) as $key) {
            $exceptions[] = $this->gpaException((string) $key, 'orphan_persisted_calculation', null, null, null, (int) $actual->get($key)->id);
        }

        $currentRows = DB::table('gpa_calculations')
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->whereIn('student_id', collect($expected)->map(fn (array $row): int => (int) $row['student_id'])->unique()->all()))
            ->where('is_current', true)
            ->get();
        foreach ($currentRows->groupBy('student_id')->filter(static fn (Collection $rows): bool => $rows->count() > 1) as $studentId => $rows) {
            $exceptions[] = [
                'invariant_code' => 'GPA-004',
                'stable_key' => 'student:'.$studentId,
                'source_table' => 'gpa_calculations',
                'target_table' => 'gpa_calculations',
                'source_ids' => [],
                'target_ids' => $rows->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                'field' => 'is_current',
                'expected' => ['max_current_rows_per_student' => 1],
                'actual' => ['current_rows' => $rows->count()],
                'severity' => 'error',
                'reason' => 'multiple_current_gpa_rows_for_student',
            ];
        }

        return [
            'counts' => [
                'expected_rows' => count($expected),
                'persisted_finalized_rows' => $actual->count(),
                'current_rows' => $currentRows->count(),
                'exceptions' => count($exceptions),
            ],
            'exceptions' => $exceptions,
            'compatibility_conditions' => [
                'current_unfinalized_rows' => $currentRows->where('is_finalized', false)->count(),
                'note' => 'The Student Hub intentionally reads the current GPA compatibility projection, including an unfinalized current row.',
            ],
        ];
    }

    /** @param array{student_id?: int, semester_id?: int} $scope @return array<string, array<string, mixed>> */
    private function expectedGpaRows(array $scope): array
    {
        $transcriptScope = $scope;
        if (isset($scope['semester_id'])) {
            $studentIds = TranscriptEntry::query()
                ->where('semester_id', $scope['semester_id'])
                ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
                ->distinct()
                ->pluck('student_id');
            $transcriptScope = ['student_ids' => $studentIds->all()];
        }
        $entries = TranscriptEntry::query()
            ->when(isset($transcriptScope['student_id']), fn ($query) => $query->where('student_id', $transcriptScope['student_id']))
            ->when(isset($transcriptScope['student_ids']), fn ($query) => $query->whereIn('student_id', $transcriptScope['student_ids']))
            ->get()
            ->groupBy('student_id');
        $rows = [];

        foreach ($entries as $studentEntries) {
            $quality = 0.0;
            $credits = 0.0;
            $earned = 0.0;
            $semesters = $studentEntries->groupBy('semester_id')->sortBy(fn (Collection $items): string => $this->semesterOrder((int) $items->first()->semester_id));

            foreach ($semesters as $semesterEntries) {
                $eligible = $semesterEntries->filter(static fn (TranscriptEntry $entry): bool => ! $entry->excluded_from_gpa && (float) $entry->credit_points > 0);
                $semesterQuality = (float) $eligible->sum(static fn (TranscriptEntry $entry): float => (float) $entry->final_percentage * (float) $entry->credit_points);
                $semesterCredits = (float) $eligible->sum('credit_points');
                $semesterEarned = (float) $eligible
                    ->filter(static fn (TranscriptEntry $entry): bool => (bool) $entry->is_passed)
                    ->sum('credit_points_earned');
                $quality += $semesterQuality;
                $credits += $semesterCredits;
                $earned += $semesterEarned;
                $studentId = (int) $semesterEntries->first()->student_id;
                $semesterId = (int) $semesterEntries->first()->semester_id;
                $programIds = $semesterEntries->pluck('program_id')->filter()->unique()->values();
                $rows[$this->gpaKey($studentId, $semesterId)] = [
                    'student_id' => $studentId,
                    'semester_id' => $semesterId,
                    'program_id' => $programIds->count() === 1 ? (int) $programIds->first() : null,
                    'semester_gpa' => $this->round($semesterCredits === 0.0 ? 0.0 : $semesterQuality / $semesterCredits, 3),
                    'cumulative_gpa' => $this->round($credits === 0.0 ? 0.0 : $quality / $credits, 3),
                    'semester_quality_points' => $this->round($semesterQuality, 3),
                    'cumulative_quality_points' => $this->round($quality, 3),
                    'semester_credit_points' => $this->round($semesterCredits, 2),
                    'cumulative_credit_points' => $this->round($credits, 2),
                    'semester_credit_points_earned' => $this->round($semesterEarned, 2),
                    'cumulative_credit_points_earned' => $this->round($earned, 2),
                    'academic_standing' => $quality / max($credits, 1.0) >= 50.0 ? 'normal' : 'warning',
                    'source_ids' => $semesterEntries->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
                    'legacy_source_ids' => $semesterEntries->pluck('course_result_id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
                ];
            }
        }

        return $rows;
    }

    /** @param array{student_id?: int, semester_id?: int} $scope @return array<string, mixed> */
    private function reconcileLifecycle(array $scope): array
    {
        $actions = DB::table('student_action_logs')
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where(function ($semesterQuery) use ($scope): void {
                $semesterQuery
                    ->where('from_semester_id', $scope['semester_id'])
                    ->orWhere('return_semester_id', $scope['semester_id'])
                    ->orWhere('intended_intake_semester_id', $scope['semester_id'])
                    ->orWhere('dropout_semester_id', $scope['semester_id'])
                    ->orWhere('effective_semester_id', $scope['semester_id']);
            }))
            ->get(['id', 'student_id', 'action_type', 'decision_id']);
        $events = DB::table('academic_progression_events')
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->get(['id', 'student_id', 'decision_id']);
        $decisionIds = $actions->pluck('decision_id')->merge($events->pluck('decision_id'))->filter()->unique();
        $decisionRows = DB::table('student_decisions')
            ->whereIn('id', $decisionIds)
            ->get(['id', 'decision_name', 'decision_number', 'decision_signer', 'issued_at', 'changed_by_user_id']);
        $existingDecisions = $decisionRows->pluck('id')->all();
        $roster = DB::table('student_decision_student')
            ->whereIn('student_decision_id', $decisionIds)
            ->get(['student_decision_id', 'student_id'])
            ->map(fn (object $row): string => $row->student_decision_id.':'.$row->student_id)
            ->flip();
        $exceptions = [];

        foreach ($actions as $action) {
            $this->appendDecisionException($exceptions, 'action-'.$action->id, (int) $action->student_id, $action->decision_id, $existingDecisions, $roster);
        }
        foreach ($events as $event) {
            $this->appendDecisionException($exceptions, 'progression-'.$event->id, (int) $event->student_id, $event->decision_id, $existingDecisions, $roster);
        }
        foreach ($decisionRows as $decision) {
            $missingFields = collect(['decision_name', 'decision_number', 'decision_signer', 'issued_at', 'changed_by_user_id'])
                ->filter(fn (string $field): bool => $decision->{$field} === null || $decision->{$field} === '')
                ->values()
                ->all();
            if ($missingFields !== []) {
                $exceptions[] = [
                    'invariant_code' => 'DEC-003',
                    'stable_key' => 'decision-'.$decision->id,
                    'source_table' => 'student_decisions',
                    'target_table' => 'student_action_logs/academic_progression_events',
                    'source_ids' => [(int) $decision->id],
                    'target_ids' => $decisionIds->contains($decision->id) ? $roster->keys()->filter(fn (string $key): bool => str_starts_with($key, $decision->id.':'))->map(fn (string $key): int => (int) str($key)->after(':'))->all() : [],
                    'field' => implode(',', $missingFields),
                    'expected' => ['required_fields_present' => true],
                    'actual' => ['missing_fields' => $missingFields],
                    'severity' => 'error',
                    'reason' => 'decision_metadata_incomplete',
                ];
            }
        }

        $missingActions = DB::table('student_action_logs')
            ->whereIn('action_type', StudentActionType::requiresDecisionValues())
            ->whereNull('decision_id')
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where(function ($semesterQuery) use ($scope): void {
                $semesterQuery
                    ->where('from_semester_id', $scope['semester_id'])
                    ->orWhere('return_semester_id', $scope['semester_id'])
                    ->orWhere('intended_intake_semester_id', $scope['semester_id'])
                    ->orWhere('dropout_semester_id', $scope['semester_id'])
                    ->orWhere('effective_semester_id', $scope['semester_id']);
            }))
            ->get(['id', 'student_id', 'action_type']);

        foreach ($missingActions as $action) {
            $exceptions[] = [
                'invariant_code' => 'DEC-001',
                'stable_key' => 'action-'.$action->id,
                'source_table' => 'student_action_logs',
                'target_table' => 'student_decisions',
                'source_ids' => [(int) $action->id],
                'target_ids' => [],
                'expected' => ['decision_required' => true, 'action_type' => $action->action_type],
                'actual' => ['decision_id' => null],
                'severity' => 'error',
                'reason' => 'required_transition_missing_decision',
            ];
        }

        $missingProgressionEvents = DB::table('academic_progression_events')
            ->whereIn('event_type', AcademicProgressionEventType::requiresDecisionValues())
            ->whereNull('decision_id')
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->get(['id', 'student_id', 'event_type']);

        foreach ($missingProgressionEvents as $event) {
            $exceptions[] = [
                'invariant_code' => 'DEC-001',
                'stable_key' => 'progression-'.$event->id,
                'source_table' => 'academic_progression_events',
                'target_table' => 'student_decisions',
                'source_ids' => [(int) $event->id],
                'target_ids' => [],
                'expected' => ['decision_required' => true, 'event_type' => $event->event_type],
                'actual' => ['decision_id' => null],
                'severity' => 'error',
                'reason' => 'required_transition_missing_decision',
            ];
        }

        return [
            'counts' => [
                'action_rows' => $actions->count(),
                'progression_event_rows' => $events->count(),
                'decision_references' => $decisionIds->count(),
                'required_decisions_missing' => $missingActions->count() + $missingProgressionEvents->count(),
                'exceptions' => count($exceptions),
            ],
            'exceptions' => $exceptions,
        ];
    }

    /** @param list<array<string, mixed>> $exceptions @param Collection<int, int> $existingDecisions @param Collection<string, int> $roster */
    private function appendDecisionException(array &$exceptions, string $stableKey, int $studentId, mixed $decisionId, array $existingDecisions, Collection $roster): void
    {
        if ($decisionId === null) {
            return;
        }
        if (! in_array((int) $decisionId, $existingDecisions, true)) {
            $exceptions[] = [
                'invariant_code' => 'DEC-001',
                'stable_key' => $stableKey,
                'source_table' => 'student_decisions',
                'target_table' => 'student_action_logs/academic_progression_events',
                'source_ids' => [(int) $decisionId],
                'target_ids' => [$studentId],
                'expected' => ['decision_id' => (int) $decisionId, 'roster_student_id' => $studentId],
                'actual' => ['decision_id' => null],
                'severity' => 'error',
                'reason' => 'decision_reference_missing',
            ];

            return;
        }
        if (! $roster->has($decisionId.':'.$studentId)) {
            $exceptions[] = [
                'invariant_code' => 'DEC-002',
                'stable_key' => $stableKey,
                'source_table' => 'student_decision_student',
                'target_table' => 'student_action_logs/academic_progression_events',
                'source_ids' => [(int) $decisionId],
                'target_ids' => [$studentId],
                'expected' => ['decision_id' => (int) $decisionId, 'roster_student_id' => $studentId],
                'actual' => ['roster_contains_student' => false],
                'severity' => 'error',
                'reason' => 'decision_roster_missing_student',
            ];
        }
    }

    /** @param array{student_id?: int, semester_id?: int} $scope @return array<string, mixed> */
    private function evaluateGraduation(array $scope): array
    {
        $studentIds = TranscriptEntry::query()
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->distinct()
            ->pluck('student_id');
        $expectedDates = DB::table('students')->whereIn('id', $studentIds)->whereNull('deleted_at')->pluck('expected_graduation_date', 'id');
        $evaluated = 0;
        $exceptions = [];
        $attemptComparison = $this->compareGraduationAttempts($studentIds->all());

        foreach ($studentIds as $studentId) {
            try {
                $expectedGraduationDate = $expectedDates->get($studentId);
                $this->graduationProgress->handle(
                    (int) $studentId,
                    is_string($expectedGraduationDate) ? $expectedGraduationDate : null,
                );
                $evaluated++;
            } catch (\Throwable $exception) {
                $exceptions[] = [
                    'invariant_code' => 'GRAD-001',
                    'stable_key' => 'student:'.$studentId,
                    'source_table' => 'transcript_entries/program_enrollments',
                    'target_table' => 'graduation_progress',
                    'source_ids' => [(int) $studentId],
                    'target_ids' => [],
                    'severity' => 'error',
                    'reason' => 'graduation_evaluation_failed:'.$exception::class,
                ];
            }
        }
        $exceptions = [...$attemptComparison['exceptions'], ...$exceptions];
        $egcConsistency = $this->compareEgcState($scope);
        $exceptions = [...$exceptions, ...$egcConsistency['exceptions']];

        $duplicateEgcBlocks = DB::table('egc_blocks')
            ->select('student_id', 'semester_id', 'block_number', DB::raw('COUNT(*) as aggregate'))
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->groupBy('student_id', 'semester_id', 'block_number')
            ->having('aggregate', '>', 1)
            ->get();
        foreach ($duplicateEgcBlocks as $block) {
            $exceptions[] = [
                'invariant_code' => 'EGC-001',
                'stable_key' => "student:{$block->student_id}:semester:{$block->semester_id}:block:{$block->block_number}",
                'source_table' => 'egc_blocks',
                'target_table' => 'egc_blocks',
                'source_ids' => [],
                'target_ids' => DB::table('egc_blocks')
                    ->where('student_id', $block->student_id)
                    ->where('semester_id', $block->semester_id)
                    ->where('block_number', $block->block_number)
                    ->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                'expected' => ['unique_business_key' => true],
                'actual' => ['count' => (int) $block->aggregate],
                'severity' => 'error',
                'reason' => 'duplicate_egc_block_business_key',
            ];
        }

        return [
            'counts' => [
                'students_evaluated' => $evaluated,
                'best_passing_attempts_compared' => $attemptComparison['counts']['compared'],
                'egc_duplicate_business_keys' => $duplicateEgcBlocks->count(),
                'egc_state_rows_compared' => $egcConsistency['counts']['compared'],
                'exceptions' => count($exceptions),
            ],
            'exceptions' => $exceptions,
            'comparison_status' => 'legacy_vs_transcript_best_passing_attempts_and_runtime_graduation_compared',
        ];
    }

    /** @param list<int>|array<int, int> $studentIds @return array<string, mixed> */
    private function compareGraduationAttempts(array $studentIds): array
    {
        $exceptions = [];
        $compared = 0;

        foreach ($studentIds as $studentId) {
            $legacy = DB::table('academic_records')
                ->where('student_id', $studentId)
                ->where('grade_status', 'final')
                ->get(['id', 'unit_id', 'course_offering_id', 'attempt_number', 'final_percentage', 'credit_points_earned', 'is_passed', 'grade_finalized_date'])
                ->filter(static fn (object $record): bool => (bool) $record->is_passed)
                ->groupBy('unit_id')
                ->map(fn (Collection $rows): ?object => $rows->sort(static fn (object $left, object $right): int => [
                    $right->grade_finalized_date ?? '',
                    (int) ($right->attempt_number ?? 0),
                    (int) $right->id,
                ] <=> [
                    $left->grade_finalized_date ?? '',
                    (int) ($left->attempt_number ?? 0),
                    (int) $left->id,
                ])->first());
            $transcript = TranscriptEntry::query()
                ->where('student_id', $studentId)
                ->get(['id', 'course_result_id', 'unit_id', 'course_offering_id', 'attempt_number', 'final_percentage', 'credit_points_earned', 'is_passed', 'finalized_at'])
                ->filter(static fn (TranscriptEntry $entry): bool => (bool) $entry->is_passed)
                ->groupBy('unit_id')
                ->map(fn (Collection $rows): ?TranscriptEntry => $rows->sort(static fn (TranscriptEntry $left, TranscriptEntry $right): int => [
                    $right->finalizedOn() ?? '',
                    (int) ($right->attempt_number ?? 0),
                    (int) $right->id,
                ] <=> [
                    $left->finalizedOn() ?? '',
                    (int) ($left->attempt_number ?? 0),
                    (int) $left->id,
                ])->first());

            foreach ($legacy->keys()->merge($transcript->keys())->unique() as $unitId) {
                $source = $legacy->get($unitId);
                $target = $transcript->get($unitId);
                $compared++;
                if ($source !== null && $target !== null && (int) $target->course_result_id === (int) $source->id) {
                    continue;
                }
                $exceptions[] = [
                    'invariant_code' => 'GRAD-002',
                    'stable_key' => "student:{$studentId}:unit:{$unitId}",
                    'source_table' => 'academic_records',
                    'target_table' => 'transcript_entries',
                    'source_ids' => $source === null ? [] : [(int) $source->id],
                    'target_ids' => $target === null ? [] : [(int) $target->id],
                    'expected' => $source === null ? null : ['course_result_id' => (int) $source->id, 'attempt_number' => $source->attempt_number],
                    'actual' => $target === null ? null : ['course_result_id' => (int) $target->course_result_id, 'attempt_number' => $target->attempt_number],
                    'severity' => 'error',
                    'reason' => 'graduation_best_passing_attempt_differs',
                ];
            }
        }

        return ['counts' => ['compared' => $compared, 'exceptions' => count($exceptions)], 'exceptions' => $exceptions];
    }

    /** @param array{student_id?: int, semester_id?: int} $scope @return array<string, mixed> */
    private function compareEgcState(array $scope): array
    {
        $blocks = DB::table('egc_blocks as block')
            ->leftJoin('students as student', 'student.id', '=', 'block.student_id')
            ->leftJoin('program_enrollments as enrollment', function ($join): void {
                $join->on('enrollment.student_id', '=', 'block.student_id')->where('enrollment.is_primary', true);
            })
            ->when(isset($scope['student_id']), fn ($query) => $query->where('block.student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('block.semester_id', $scope['semester_id']))
            ->whereNull('student.deleted_at')
            ->get([
                'block.id', 'block.student_id', 'block.semester_id', 'block.block_number', 'block.level_number', 'block.result', 'block.attendance_rate', 'block.is_retake',
                'student.gc_starting_level', 'student.gc_current_level', 'student.gc_total_levels',
                'enrollment.id as enrollment_id', 'enrollment.egc_starting_level', 'enrollment.egc_current_level', 'enrollment.egc_total_levels',
            ]);
        $exceptions = [];

        foreach ($blocks as $block) {
            $missingEnrollment = $block->enrollment_id === null;
            $stateFields = [
                'egc_starting_level' => [$block->gc_starting_level, $block->egc_starting_level],
                'egc_current_level' => [$block->gc_current_level, $block->egc_current_level],
                'egc_total_levels' => [$block->gc_total_levels, $block->egc_total_levels],
            ];
            $differences = collect($stateFields)
                ->filter(fn (array $values): bool => $this->normalize($values[0]) !== $this->normalize($values[1]))
                ->keys()
                ->values()
                ->all();
            if ($missingEnrollment || $differences !== []) {
                $exceptions[] = [
                    'invariant_code' => 'EGC-002',
                    'stable_key' => "student:{$block->student_id}:semester:{$block->semester_id}:block:{$block->block_number}",
                    'source_table' => 'students',
                    'target_table' => 'program_enrollments',
                    'source_ids' => [(int) $block->student_id],
                    'target_ids' => $block->enrollment_id === null ? [] : [(int) $block->enrollment_id],
                    'field' => $missingEnrollment ? 'program_enrollment' : implode(',', $differences),
                    'expected' => $missingEnrollment ? ['primary_enrollment_required' => true] : collect($stateFields)->mapWithKeys(fn (array $values, string $field): array => [$field => $values[0]])->all(),
                    'actual' => $missingEnrollment ? null : collect($stateFields)->mapWithKeys(fn (array $values, string $field): array => [$field => $values[1]])->all(),
                    'severity' => 'error',
                    'reason' => 'egc_legacy_and_progression_state_differs',
                ];
            }
            if ($block->attendance_rate !== null && ((float) $block->attendance_rate < 0 || (float) $block->attendance_rate > 100)) {
                $exceptions[] = [
                    'invariant_code' => 'EGC-003',
                    'stable_key' => "egc-block:{$block->id}",
                    'source_table' => 'egc_blocks',
                    'target_table' => 'egc_blocks',
                    'source_ids' => [(int) $block->id],
                    'target_ids' => [(int) $block->id],
                    'field' => 'attendance_rate',
                    'expected' => ['min' => 0, 'max' => 100],
                    'actual' => (float) $block->attendance_rate,
                    'severity' => 'error',
                    'reason' => 'egc_attendance_rate_out_of_range',
                ];
            }
        }

        return ['counts' => ['compared' => $blocks->count(), 'exceptions' => count($exceptions)], 'exceptions' => $exceptions];
    }

    /** @param array{student_id?: int, semester_id?: int} $scope @return int */
    private function legacyOutcomesWithoutTranscript(array $scope): int
    {
        return DB::table('academic_records')
            ->where('grade_status', 'final')
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->whereNotExists(fn ($query) => $query->select(DB::raw(1))
                ->from('transcript_entries')
                ->whereColumn('transcript_entries.course_result_id', 'academic_records.id'))
            ->count();
    }

    /** @param array{student_id?: int, semester_id?: int} $scope @return array<string, mixed> */
    private function reconcileStudentHubEvidence(array $scope): array
    {
        $studentIds = DB::table('academic_records')
            ->where('grade_status', 'final')
            ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
            ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
            ->pluck('student_id')
            ->merge(TranscriptEntry::query()
                ->when(isset($scope['student_id']), fn ($query) => $query->where('student_id', $scope['student_id']))
                ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
                ->pluck('student_id'))
            ->filter()
            ->unique()
            ->sort()
            ->values();
        $exceptions = [];
        $compared = 0;

        foreach ($studentIds as $studentId) {
            $legacy = collect($this->studentHubOutcomes->forStudent((int) $studentId))
                ->filter(fn (StudentHubCourseOutcomeEvidence $outcome): bool => $outcome->gradeStatus === 'final')
                ->when(isset($scope['semester_id']), fn (Collection $rows): Collection => $rows->where('semesterId', $scope['semester_id']))
                ->keyBy('courseResultId');
            $transcripts = TranscriptEntry::query()
                ->where('student_id', $studentId)
                ->when(isset($scope['semester_id']), fn ($query) => $query->where('semester_id', $scope['semester_id']))
                ->get()
                ->keyBy('course_result_id');

            foreach ($legacy->keys()->merge($transcripts->keys())->unique() as $courseResultId) {
                $source = $legacy->get($courseResultId);
                $target = $transcripts->get($courseResultId);
                $compared++;
                $differences = [];
                $expectedValues = [];
                $actualValues = [];
                foreach (['courseOfferingId', 'unitId', 'semesterId', 'finalPercentage', 'finalLetterGrade', 'gradeStatus', 'completionStatus', 'isPassed', 'creditPoints', 'creditPointsEarned', 'attemptNumber', 'excludedFromGpa'] as $field) {
                    $sourceValue = $source?->{$field};
                    $targetValue = match ($field) {
                        'courseOfferingId' => $target?->course_offering_id,
                        'unitId' => $target?->unit_id,
                        'semesterId' => $target?->semester_id,
                        'finalPercentage' => $target?->final_percentage === null ? null : (float) $target->final_percentage,
                        'finalLetterGrade' => $target?->final_letter_grade,
                        'gradeStatus' => $target === null ? null : 'final',
                        'completionStatus' => $target?->completion_status,
                        'isPassed' => $target?->is_passed,
                        'creditPoints' => $target?->credit_points === null ? null : (float) $target->credit_points,
                        'creditPointsEarned' => $target?->credit_points_earned === null ? null : (float) $target->credit_points_earned,
                        'attemptNumber' => $target?->attempt_number,
                        'excludedFromGpa' => $target?->excluded_from_gpa,
                    };
                    $expectedValues[$field] = $sourceValue;
                    $actualValues[$field] = $targetValue;
                    if ($this->normalize($sourceValue) !== $this->normalize($targetValue)) {
                        $differences[] = $field;
                    }
                }
                if ($differences !== []) {
                    $exceptions[] = [
                        'invariant_code' => 'HUB-001',
                        'stable_key' => 'course-result:'.$courseResultId,
                        'source_table' => 'academic_records',
                        'target_table' => 'transcript_entries',
                        'source_ids' => $source === null ? [] : [(int) $courseResultId],
                        'target_ids' => $target === null ? [] : [(int) $target->id],
                        'field' => implode(',', $differences),
                        'expected' => $source === null ? null : $expectedValues,
                        'actual' => $target === null ? null : $actualValues,
                        'severity' => 'error',
                        'reason' => 'student_hub_consumer_evidence_differs_from_transcript',
                    ];
                }
            }
        }

        return [
            'counts' => ['students_compared' => $studentIds->count(), 'course_outcomes_compared' => $compared, 'exceptions' => count($exceptions)],
            'legacy_only_compatibility_fields' => ['gradePoints', 'isRepeatCourse', 'meetsAttendanceRequirement', 'gradeDisplay'],
            'exceptions' => $exceptions,
        ];
    }

    /** @return array{supported_runtime_consumers: list<array{path: string, reason: string}>, retirement_candidates: list<string>} */
    private function consumerInventory(): array
    {
        return [
            'supported_runtime_consumers' => [
                ['path' => 'App\\Shared\\Contracts\\Academic\\StudentHubCourseOutcomeEvidenceReader', 'reason' => 'Student Hub, export, registration, scores, and graduation compatibility evidence.'],
                ['path' => 'App\\Modules\\Finance\\Actions\\Egc\\SyncEgcBlockResultsAction', 'reason' => 'Finance EGC reconciliation business path.'],
                ['path' => 'App\\Services\\V1\\Student\\GradeService', 'reason' => 'Supported student grades and GPA API.'],
                ['path' => 'App\\Services\\V1\\Student\\DashboardService', 'reason' => 'Supported student dashboard GPA and credit-progress API.'],
                ['path' => 'App\\Services\\V1\\Student\\ProfileService', 'reason' => 'Supported student academic-history API.'],
                ['path' => 'App\\Services\\V1\\Student\\CurriculumService', 'reason' => 'Supported student curriculum and prerequisite API.'],
                ['path' => 'App\\Modules\\Academic\\Delivery\\Queries\\GetLecturerCourseGradebookQuery', 'reason' => 'Supported lecturer gradebook/reporting path.'],
                ['path' => 'App\\Modules\\Academic\\Delivery\\Support\\LecturerCourseService', 'reason' => 'Supported lecturer course student list and academic-record path.'],
                ['path' => 'App\\Modules\\Academic\\Delivery\\Support\\AssessmentReportService', 'reason' => 'Supported lecturer assessment matrix and export path.'],
                ['path' => 'App\\Modules\\Academic\\Support\\AcademicFinanceChargeSourceGateway', 'reason' => 'Supported Finance charge-source read.'],
                ['path' => 'App\\Modules\\Academic\\Progression\\Actions\\ProcessEgcCourseResultsAction', 'reason' => 'Supported EGC progression and state transition path.'],
                ['path' => 'App\\Services\\V1\\Student\\PrerequisiteValidationService', 'reason' => 'Supported registration prerequisite gate.'],
                ['path' => 'App\\Modules\\Academic\\Delivery\\Actions\\CommitCourseResultsAndTranscriptEntriesAction', 'reason' => 'Supported course-result/transcript dual-write path.'],
                ['path' => 'App\\Console\\Commands\\SyncAcademicRecordsCommand', 'reason' => 'Supported legacy academic-record generation/synchronization path.'],
                ['path' => 'App\\Modules\\Academic\\Support\\AiAcademicStudentProfileReader', 'reason' => 'Supported AI academic profile compatibility path tracked by migration debt.'],
            ],
            'retirement_candidates' => [
                'App\\Shared\\Contracts\\Academic\\LegacyTranscriptOutcomeReader (after an explicit removal approval and command migration)',
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function gpaException(string $key, string $reason, ?string $field, mixed $expected, mixed $actual = null, ?int $targetId = null, array $targetIds = []): array
    {
        $expectedValue = is_array($expected) && $field !== null ? ($expected[$field] ?? null) : $expected;
        $sourceIds = is_array($expected) ? array_values(array_map('intval', $expected['source_ids'] ?? [])) : [];

        return [
            'invariant_code' => 'GPA-001',
            'stable_key' => $key,
            'source_table' => 'transcript_entries',
            'target_table' => 'gpa_calculations',
            'source_ids' => $sourceIds,
            'target_ids' => $targetIds !== [] ? $targetIds : ($targetId === null ? [] : [$targetId]),
            'field' => $field,
            'expected' => $expectedValue,
            'actual' => $actual,
            'severity' => 'error',
            'reason' => $reason,
        ];
    }

    /** @return array{list<int>, list<int>} */
    private function derivedEvidenceIds(string $key): array
    {
        if (preg_match('/^student:(\d+):semester:(\d+)$/', $key, $matches) === 1) {
            $studentId = (int) $matches[1];
            $semesterId = (int) $matches[2];

            return [
                DB::table('academic_records')->where('student_id', $studentId)->where('semester_id', $semesterId)->where('grade_status', 'final')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                TranscriptEntry::query()->where('student_id', $studentId)->where('semester_id', $semesterId)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            ];
        }
        if (preg_match('/^student:(\d+):unit:(\d+)$/', $key, $matches) === 1) {
            $studentId = (int) $matches[1];
            $unitId = (int) $matches[2];

            return [
                DB::table('academic_records')->where('student_id', $studentId)->where('unit_id', $unitId)->where('grade_status', 'final')->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
                TranscriptEntry::query()->where('student_id', $studentId)->where('unit_id', $unitId)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all(),
            ];
        }

        return [[], []];
    }

    private function gpaKey(int $studentId, int $semesterId): string
    {
        return "student:{$studentId}:semester:{$semesterId}";
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
        return GpaValueComparator::normalize($value);
    }

    private function round(float $value, int $precision): float
    {
        return round($value, $precision);
    }
}
