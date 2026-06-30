<?php

declare(strict_types=1);

namespace App\Modules\Academic\Queries;

use App\Enums\AcademicProgressionEventType;
use App\Models\AcademicProgressionEvent;
use App\Models\IeltsCertificate;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Unified Lifecycle timeline (ADR-0009).
 *
 * Builds the Lifecycle tab's read contract: a single chronological timeline
 * merged from BOTH lifecycle streams — status actions (`StudentActionLog`) and
 * status-changing EGC progression events (`AcademicProgressionEvent`) — ordered
 * by event time, newest first, with each authorizing Decision rendered inline.
 *
 * Non-status EGC progression (English-level changes, IELTS records) is kept OUT
 * of the main timeline and returned under `egc` as a secondary detail panel,
 * exactly as ADR-0009 prescribes. A future reader should not "fix" the merge by
 * splitting the tab back into two tables — the merge is the feature.
 */
class GetStudentLifecycleTimelineQuery
{
    /**
     * Progression event types that represent a status change and therefore
     * belong in the main timeline alongside status actions.
     *
     * @var array<int, AcademicProgressionEventType>
     */
    private const MAIN_TIMELINE_PROGRESSION = [
        AcademicProgressionEventType::PLACEMENT_INITIALIZED,
        AcademicProgressionEventType::COURSE_STAGE_CHANGED,
    ];

    /**
     * Build the merged timeline plus the EGC sub-panel for one student.
     *
     * @return array{
     *     timeline: array<int, array<string, mixed>>,
     *     egc: array{
     *         current_level: int|null,
     *         starting_level: int|null,
     *         history: array<int, array<string, mixed>>,
     *         ielts: array<int, array<string, mixed>>,
     *     }
     * }
     */
    public function handle(Student $student): array
    {
        $actions = $student->actionLogs()
            ->with(['decision.uploadRecord', 'changedBy:id,name', 'fromCampus:id,name,code', 'toCampus:id,name,code'])
            ->get();

        $events = $student->academicProgressionEvents()
            ->with(['decision.uploadRecord', 'createdBy:id,name', 'semester:id,name,code'])
            ->get();

        $entries = [];

        foreach ($actions as $action) {
            $entries[] = $this->actionRow($action);
        }

        foreach ($events as $event) {
            if (in_array($event->event_type, self::MAIN_TIMELINE_PROGRESSION, true)) {
                $entries[] = $this->progressionRow($event);
            }
        }

        usort($entries, fn (array $a, array $b): int => $this->compareEntries($a, $b));

        $timeline = array_map(static fn (array $entry): array => $entry['row'], $entries);

        return [
            'timeline' => $timeline,
            'egc' => $this->egcPanel($student, $events),
        ];
    }

    /**
     * Newest-first comparator with a deterministic, lossless tie-break:
     * event time desc, then source, then row id desc. Same-second events from
     * the two streams therefore keep a stable order instead of colliding.
     *
     * @param  array{ts: int, source: string, source_id: int, row: array<string, mixed>}  $a
     * @param  array{ts: int, source: string, source_id: int, row: array<string, mixed>}  $b
     */
    private function compareEntries(array $a, array $b): int
    {
        return [$b['ts'], $a['source'], $b['source_id']] <=> [$a['ts'], $b['source'], $a['source_id']];
    }

    /**
     * @return array{ts: int, source: string, source_id: int, row: array<string, mixed>}
     */
    private function actionRow(StudentActionLog $action): array
    {
        $type = $action->action_type;
        $occurredAt = $action->effective_at ?? $action->created_at;
        $requires = $type->requiresDecision();

        return [
            'ts' => $occurredAt?->getTimestamp() ?? 0,
            'source' => 'action',
            'source_id' => $action->id,
            'row' => [
                'id' => 'action-'.$action->id,
                'source' => 'action',
                'source_id' => $action->id,
                'type' => $type->value,
                'label' => $type->labelEn(),
                'occurred_at' => $this->iso($occurredAt),
                'requires_decision' => $requires,
                'missing_decision' => $requires && $action->decision_id === null,
                'decision' => $this->decisionPayload($action->decision),
                'actor' => $this->actorPayload($action->changedBy),
                'detail' => [
                    'reason' => $action->reason,
                    'notes' => $action->notes,
                    'previous_status' => $action->previous_status,
                    'new_status' => $action->new_status,
                    'from_campus' => $action->fromCampus ? [
                        'id' => $action->fromCampus->id,
                        'name' => $action->fromCampus->name,
                        'code' => $action->fromCampus->code,
                    ] : null,
                    'to_campus' => $action->toCampus ? [
                        'id' => $action->toCampus->id,
                        'name' => $action->toCampus->name,
                        'code' => $action->toCampus->code,
                    ] : null,
                ],
            ],
        ];
    }

    /**
     * @return array{ts: int, source: string, source_id: int, row: array<string, mixed>}
     */
    private function progressionRow(AcademicProgressionEvent $event): array
    {
        $type = $event->event_type;
        $occurredAt = $event->effective_at ?? $event->created_at;
        $requires = $type->requiresDecision();

        return [
            'ts' => $occurredAt?->getTimestamp() ?? 0,
            'source' => 'progression',
            'source_id' => $event->id,
            'row' => [
                'id' => 'progression-'.$event->id,
                'source' => 'progression',
                'source_id' => $event->id,
                'type' => $type->value,
                'label' => $type->labelEn(),
                'occurred_at' => $this->iso($occurredAt),
                'requires_decision' => $requires,
                'missing_decision' => $requires && $event->decision_id === null,
                'decision' => $this->decisionPayload($event->decision),
                'actor' => $this->actorPayload($event->createdBy),
                'detail' => [
                    'notes' => $event->notes,
                    'semester' => $event->semester ? [
                        'id' => $event->semester->id,
                        'name' => $event->semester->name,
                        'code' => $event->semester->code,
                    ] : null,
                    'from_course_stage' => $event->from_course_stage,
                    'to_course_stage' => $event->to_course_stage,
                    'from_english_level' => $event->from_english_level,
                    'to_english_level' => $event->to_english_level,
                ],
            ],
        ];
    }

    /**
     * The EGC secondary detail panel (ADR-0009): English-level / IELTS
     * progression history kept out of the main timeline, plus the student's
     * level snapshot and IELTS certificate records.
     *
     * @param  Collection<int, AcademicProgressionEvent>  $events
     * @return array{current_level: int|null, starting_level: int|null, history: array<int, array<string, mixed>>, ielts: array<int, array<string, mixed>>}
     */
    private function egcPanel(Student $student, Collection $events): array
    {
        $history = $events
            ->filter(fn (AcademicProgressionEvent $event): bool => ! in_array($event->event_type, self::MAIN_TIMELINE_PROGRESSION, true))
            ->sortByDesc(fn (AcademicProgressionEvent $event): int => ($event->effective_at ?? $event->created_at)?->getTimestamp() ?? 0)
            ->values()
            ->map(fn (AcademicProgressionEvent $event): array => [
                'id' => $event->id,
                'event_type' => $event->event_type->value,
                'label' => $event->event_type->labelEn(),
                'occurred_at' => $this->iso($event->effective_at ?? $event->created_at),
                'from_english_level' => $event->from_english_level,
                'to_english_level' => $event->to_english_level,
                'trigger_source' => $event->trigger_source?->value,
                'actor' => $this->actorPayload($event->createdBy),
                'notes' => $event->notes,
            ])
            ->all();

        $ielts = $student->ieltsCertificates()
            ->with('uploadRecord')
            ->latestFirst()
            ->get()
            ->map(fn (IeltsCertificate $certificate): array => [
                'id' => $certificate->id,
                'overall_score' => $certificate->overall_score,
                'issue_date' => $this->iso($certificate->issue_date),
                'missing_documents' => (bool) $certificate->missing_documents,
                'upload_record_id' => $certificate->upload_record_id,
                'upload_record' => $certificate->uploadRecord !== null ? [
                    'id' => $certificate->uploadRecord->id,
                    'url' => $certificate->uploadRecord->url,
                    'original_name' => $certificate->uploadRecord->original_name,
                ] : null,
                'notes' => $certificate->notes,
            ])
            ->all();

        return [
            'current_level' => $student->gc_current_level !== null ? (int) $student->gc_current_level : null,
            'starting_level' => $student->gc_starting_level !== null ? (int) $student->gc_starting_level : null,
            'history' => $history,
            'ielts' => $ielts,
        ];
    }

    /**
     * @return array{id: int, decision_name: string|null, decision_number: string|null, decision_signer: string|null, issued_at: string|null, upload_record: array{id: int, url: string, original_name: string}|null}|null
     */
    private function decisionPayload(?StudentDecision $decision): ?array
    {
        if ($decision === null) {
            return null;
        }

        return [
            'id' => $decision->id,
            'decision_name' => $decision->decision_name,
            'decision_number' => $decision->decision_number,
            'decision_signer' => $decision->decision_signer,
            'issued_at' => $this->iso($decision->issued_at),
            'upload_record' => $decision->uploadRecord !== null ? [
                'id' => $decision->uploadRecord->id,
                'url' => $decision->uploadRecord->url,
                'original_name' => $decision->uploadRecord->original_name,
            ] : null,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function actorPayload(?User $actor): ?array
    {
        if ($actor === null) {
            return null;
        }

        return [
            'id' => $actor->id,
            'name' => $actor->name,
        ];
    }

    private function iso(?Carbon $value): ?string
    {
        return $value?->toIso8601String();
    }
}
