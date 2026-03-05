<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Models\Lecture;
use App\Models\Student;
use App\Models\User;

class RecipientResolver
{
    /**
     * @param  array<int, array{type:string,id:int}>  $targets
     * @return array{resolved_user_ids:array<int, int>, unresolved:array<int, array{type:string,id:int,reason:string}>}
     */
    public function resolve(array $targets, ?int $campusId): array
    {
        $resolvedUserIds = [];
        $unresolved = [];

        foreach ($targets as $target) {
            $type = strtolower((string) ($target['type'] ?? ''));
            $id = (int) ($target['id'] ?? 0);

            if ($id <= 0 || $type === '') {
                $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'invalid_target'];

                continue;
            }

            if ($type === 'user' || $type === User::class || str_ends_with($type, '\\user')) {
                $user = User::query()->find($id);
                if (! $user) {
                    $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'user_not_found'];

                    continue;
                }

                if ($campusId && ! $user->campusUserRoles()->where('campus_id', $campusId)->exists()) {
                    $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'campus_mismatch'];

                    continue;
                }

                $resolvedUserIds[] = (int) $user->id;

                continue;
            }

            if ($type === 'student' || $type === Student::class || str_ends_with($type, '\\student')) {
                $student = Student::query()->find($id);
                if (! $student || ! $student->user_id) {
                    $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'student_user_not_found'];

                    continue;
                }

                if ($campusId && (int) $student->campus_id !== $campusId) {
                    $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'campus_mismatch'];

                    continue;
                }

                $resolvedUserIds[] = (int) $student->user_id;

                continue;
            }

            if ($type === 'lecture' || $type === 'lecturer' || $type === Lecture::class || str_ends_with($type, '\\lecture')) {
                $lecture = Lecture::query()->find($id);
                if (! $lecture || ! $lecture->user_id) {
                    $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'lecture_user_not_found'];

                    continue;
                }

                if ($campusId && (int) $lecture->campus_id !== $campusId) {
                    $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'campus_mismatch'];

                    continue;
                }

                $resolvedUserIds[] = (int) $lecture->user_id;

                continue;
            }

            $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'unsupported_target_type'];
        }

        return [
            'resolved_user_ids' => array_values(array_unique($resolvedUserIds)),
            'unresolved' => $unresolved,
        ];
    }
}
