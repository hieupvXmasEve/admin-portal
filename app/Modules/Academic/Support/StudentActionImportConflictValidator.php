<?php

declare(strict_types=1);

namespace App\Modules\Academic\Support;

use App\Models\StudentActionLog;

class StudentActionImportConflictValidator
{
    public function validate(array $payload): array
    {
        $studentId = (int) ($payload['student_id'] ?? 0);
        $actionType = (string) ($payload['action_type'] ?? '');
        $targetSemesterId = $this->targetSemesterId($payload);

        if ($studentId <= 0 || $actionType === '' || $targetSemesterId === null) {
            return [
                'ok' => false,
                'errors' => ['Cannot validate existing action conflicts: missing student/action/semester mapping.'],
            ];
        }

        $existingActions = StudentActionLog::query()
            ->where('student_id', $studentId)
            ->where(function ($query) use ($targetSemesterId) {
                $query->where('from_semester_id', $targetSemesterId)
                    ->orWhere('return_semester_id', $targetSemesterId)
                    ->orWhere('dropout_semester_id', $targetSemesterId)
                    ->orWhere('effective_semester_id', $targetSemesterId)
                    ->orWhere('intended_intake_semester_id', $targetSemesterId);
            })
            ->orderByDesc('id')
            ->get();

        if ($existingActions->isEmpty()) {
            return ['ok' => true, 'mode' => 'create'];
        }

        $differentType = $existingActions->first(fn ($log) => $log->action_type->value !== $actionType);
        if ($differentType) {
            return [
                'ok' => false,
                'errors' => [
                    'Student already has a different action type in this semester. Import is not allowed.',
                ],
            ];
        }

        $matched = $existingActions->first(fn ($log) => $this->isSamePeriod($payload, $log));
        if (! $matched) {
            return [
                'ok' => false,
                'errors' => [
                    'Student already has this action type but semester period does not match existing record.',
                ],
            ];
        }

        return [
            'ok' => true,
            'mode' => 'append',
            'existing_action_log_id' => $matched->id,
        ];
    }

    private function targetSemesterId(array $payload): ?int
    {
        return match ($payload['action_type'] ?? '') {
            'ACADEMIC_DEFER' => $payload['from_semester_id'] ?? null,
            'ACADEMIC_RESUME' => $payload['return_semester_id'] ?? null,
            'ACADEMIC_DROPOUT' => $payload['dropout_semester_id'] ?? null,
            'CAMPUS_TRANSFER' => $payload['effective_semester_id'] ?? null,
            default => null,
        };
    }

    private function isSamePeriod(array $payload, StudentActionLog $existing): bool
    {
        $actionType = (string) ($payload['action_type'] ?? '');

        if ($actionType === 'ACADEMIC_DEFER') {
            return (int) ($payload['from_semester_id'] ?? 0) === (int) $existing->from_semester_id
                && (int) ($payload['return_semester_id'] ?? 0) === (int) $existing->return_semester_id;
        }

        if ($actionType === 'ACADEMIC_RESUME') {
            return (int) ($payload['return_semester_id'] ?? 0) === (int) $existing->return_semester_id;
        }

        if ($actionType === 'ACADEMIC_DROPOUT') {
            return (int) ($payload['dropout_semester_id'] ?? 0) === (int) $existing->dropout_semester_id;
        }

        if ($actionType === 'CAMPUS_TRANSFER') {
            return (int) ($payload['effective_semester_id'] ?? 0) === (int) $existing->effective_semester_id;
        }

        return false;
    }
}
