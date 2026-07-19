<?php

declare(strict_types=1);

namespace App\Modules\Academic\Progression\Actions;

use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Modules\Academic\Queries\PreviewStudentDecisionBulkLinkQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class BulkLinkStudentsToDecisionAction
{
    /**
     * @param  array{decision_id: int, student_codes: list<string>, action_type: string, user_id: int, campus_id?: int|null, input_code_count?: int|null}  $data
     */
    public static function run(array $data): array
    {
        $decision = StudentDecision::query()->findOrFail($data['decision_id']);
        $studentCodes = $data['student_codes'];
        $actionType = $data['action_type'];
        $userId = $data['user_id'];
        $campusId = $data['campus_id'] ?? null;
        $inputCodeCount = $data['input_code_count'] ?? null;

        $preview = app(PreviewStudentDecisionBulkLinkQuery::class)
            ->handle($decision, $studentCodes, $actionType, $campusId, $inputCodeCount);

        $actionLogIds = collect($preview['students'])
            ->flatMap(fn (array $row): array => collect($row['action_logs'])->pluck('id')->all())
            ->values()
            ->all();

        if ($actionLogIds === []) {
            Log::info('Student decision bulk link completed', [
                'decision_id' => $decision->id,
                'action_type' => $actionType,
                'input_code_count' => $inputCodeCount ?? count($studentCodes),
                'unique_code_count' => count($studentCodes),
                'requested_action_count' => 0,
                'linked_action_count' => 0,
                'skipped_student_count' => $preview['summary']['skipped_students_count'],
                'changed_by' => $userId,
            ]);

            return [
                'decision_id' => $decision->id,
                'action_type' => $actionType,
                'linked_action_count' => 0,
                'requested_action_count' => 0,
                'preview' => $preview,
            ];
        }

        $linkedActionCount = DB::transaction(function () use ($decision, $actionLogIds, $actionType, $campusId): int {
            $count = StudentActionLog::query()
                ->whereKey($actionLogIds)
                ->whereNull('decision_id')
                ->where('action_type', $actionType)
                ->when($campusId, fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId)))
                ->update(['decision_id' => $decision->id]);

            // Keep the coverage roster in step with the links just made (ADR-0008):
            // one decision covers the many students it now authorizes.
            $linkedStudentIds = StudentActionLog::query()
                ->whereKey($actionLogIds)
                ->where('decision_id', $decision->id)
                ->distinct()
                ->pluck('student_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            $decision->cover(...$linkedStudentIds);

            return $count;
        });

        Log::info('Student decision bulk link completed', [
            'decision_id' => $decision->id,
            'action_type' => $actionType,
            'input_code_count' => $inputCodeCount ?? count($studentCodes),
            'unique_code_count' => count($studentCodes),
            'requested_action_count' => count($actionLogIds),
            'linked_action_count' => $linkedActionCount,
            'skipped_student_count' => $preview['summary']['skipped_students_count'],
            'changed_by' => $userId,
        ]);

        return [
            'decision_id' => $decision->id,
            'action_type' => $actionType,
            'linked_action_count' => $linkedActionCount,
            'requested_action_count' => count($actionLogIds),
            'preview' => $preview,
        ];
    }
}
