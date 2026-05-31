<?php

declare(strict_types=1);

namespace App\Modules\Academic\Actions;

use App\Models\StudentActionLog;
use App\Models\StudentDecision;
use App\Modules\Academic\Queries\PreviewStudentDecisionBulkLinkQuery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BulkLinkStudentsToDecisionAction
{
    /**
     * @param  list<string>  $studentCodes
     */
    public static function run(StudentDecision $decision, array $studentCodes, string $actionType, int $userId, ?int $campusId = null, ?int $inputCodeCount = null): array
    {
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
            return StudentActionLog::query()
                ->whereKey($actionLogIds)
                ->whereNull('decision_id')
                ->where('action_type', $actionType)
                ->when($campusId, fn ($query) => $query->whereHas('student', fn ($studentQuery) => $studentQuery->where('campus_id', $campusId)))
                ->update(['decision_id' => $decision->id]);
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
