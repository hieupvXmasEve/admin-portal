<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Queries;

use App\Enums\StudentActionType;
use App\Models\Student;
use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PreviewStudentDecisionBulkLinkQuery
{
    /**
     * @param  list<string>  $studentCodes
     */
    public function handle(StudentDecision $decision, array $studentCodes, string $actionType, ?int $campusId = null, ?int $inputCodeCount = null): array
    {
        $students = Student::query()
            ->select(['id', 'student_id', 'full_name', 'campus_id', 'status'])
            ->with('campus:id,name,code')
            ->whereIn('student_id', $studentCodes)
            ->when($campusId, fn ($query) => $query->where('campus_id', $campusId))
            ->get()
            ->keyBy(fn (Student $student): string => Str::upper((string) $student->student_id));

        $actionLogs = $this->actionLogsFor($students, $actionType);

        $rows = [];
        $unmatchedCodes = [];

        foreach ($studentCodes as $code) {
            /** @var Student|null $student */
            $student = $students->get($code);

            if (! $student) {
                $unmatchedCodes[] = $code;

                continue;
            }

            $studentLogs = $actionLogs->get($student->id, collect());
            $linkableLogs = $studentLogs->filter(fn (StudentActionLog $log): bool => $log->decision_id === null)->values();
            $alreadyLinkedToThis = $studentLogs->filter(fn (StudentActionLog $log): bool => (int) $log->decision_id === (int) $decision->id)->count();
            $alreadyLinkedToOther = $studentLogs
                ->filter(fn (StudentActionLog $log): bool => $log->decision_id !== null && (int) $log->decision_id !== (int) $decision->id)
                ->count();

            $rows[] = [
                'code' => $code,
                'student' => [
                    'id' => $student->id,
                    'student_id' => $student->student_id,
                    'full_name' => $student->full_name,
                    'status' => $student->status,
                    'campus' => $student->campus ? [
                        'id' => $student->campus->id,
                        'name' => $student->campus->name,
                        'code' => $student->campus->code,
                    ] : null,
                ],
                'action_logs' => $linkableLogs
                    ->map(fn (StudentActionLog $log): array => $this->formatActionLog($log))
                    ->all(),
                'linkable_action_count' => $linkableLogs->count(),
                'total_action_count' => $studentLogs->count(),
                'already_linked_to_this_count' => $alreadyLinkedToThis,
                'already_linked_to_other_count' => $alreadyLinkedToOther,
                'skipped_reason' => $this->skippedReason($studentLogs->count(), $linkableLogs->count(), $alreadyLinkedToThis, $alreadyLinkedToOther),
            ];
        }

        $linkableActionCount = collect($rows)->sum('linkable_action_count');
        $matchedCount = count($rows);

        return [
            'decision_id' => $decision->id,
            'action_type' => $actionType,
            'students' => $rows,
            'unmatched_codes' => $unmatchedCodes,
            'summary' => [
                'input_count' => $inputCodeCount ?? count($studentCodes),
                'unique_count' => count($studentCodes),
                'duplicate_count' => max(0, ($inputCodeCount ?? count($studentCodes)) - count($studentCodes)),
                'matched_count' => $matchedCount,
                'unmatched_count' => count($unmatchedCodes),
                'linkable_students_count' => collect($rows)->filter(fn (array $row): bool => $row['linkable_action_count'] > 0)->count(),
                'skipped_students_count' => collect($rows)->filter(fn (array $row): bool => $row['linkable_action_count'] === 0)->count(),
                'linkable_action_count' => $linkableActionCount,
            ],
        ];
    }

    /**
     * @param  EloquentCollection<int, Student>  $students
     * @return Collection<int, Collection<int, StudentActionLog>>
     */
    private function actionLogsFor(EloquentCollection $students, string $actionType): Collection
    {
        if ($students->isEmpty()) {
            return collect();
        }

        return StudentActionLog::query()
            ->with('decision:id,decision_name,decision_number')
            ->whereIn('student_id', $students->pluck('id'))
            ->where('action_type', $actionType)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('student_id');
    }

    private function formatActionLog(StudentActionLog $log): array
    {
        $actionType = $log->action_type;
        $actionTypeValue = $actionType instanceof StudentActionType ? $actionType->value : (string) $actionType;

        return [
            'id' => $log->id,
            'action_type' => $actionTypeValue,
            'action_type_label' => $log->action_type_label,
            'reason' => $log->reason,
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    private function skippedReason(int $totalLogs, int $linkableLogs, int $alreadyLinkedToThis, int $alreadyLinkedToOther): ?string
    {
        if ($linkableLogs > 0) {
            return null;
        }

        if ($totalLogs === 0) {
            return 'No matching student action logs found.';
        }

        if ($alreadyLinkedToThis > 0 && $alreadyLinkedToOther === 0) {
            return 'All action logs are already linked to this decision.';
        }

        if ($alreadyLinkedToOther > 0) {
            return 'All action logs are already linked to another decision.';
        }

        return 'No linkable action logs found.';
    }
}
