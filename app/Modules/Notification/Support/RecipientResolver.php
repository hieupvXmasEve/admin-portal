<?php

declare(strict_types=1);

namespace App\Modules\Notification\Support;

use App\Models\Department;
use App\Models\DepartmentMembership;
use App\Models\Lecture;
use App\Models\Student;
use App\Models\User;

class RecipientResolver
{
    /**
     * @param  array<int, array{type:string,id?:int,email?:string}>  $targets
     * @return array{resolved_recipients:array<int, array{key:string,user_id:int|null,email:string|null,campus_id:int|null}>, unresolved:array<int, array{type:string,id:int,reason:string}>}
     */
    public function resolve(array $targets, ?int $campusId): array
    {
        $resolvedRecipients = [];
        $unresolved = [];

        foreach ($targets as $target) {
            $type = strtolower((string) ($target['type'] ?? ''));
            $id = (int) ($target['id'] ?? 0);

            if ($type === 'email') {
                $email = mb_strtolower(trim((string) ($target['email'] ?? '')));
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $unresolved[] = ['type' => $type, 'id' => 0, 'reason' => 'invalid_email_target'];

                    continue;
                }

                $resolvedRecipients[] = $this->externalEmailRecipient($email);

                continue;
            }

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

                $resolvedRecipients[] = $this->userRecipient((int) $user->id);

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

                $resolvedRecipients[] = $this->userRecipient((int) $student->user_id, $student->campus_id !== null ? (int) $student->campus_id : null);

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

                $resolvedRecipients[] = $this->userRecipient((int) $lecture->user_id, $lecture->campus_id !== null ? (int) $lecture->campus_id : null);

                continue;
            }

            if ($type === 'department') {
                if (! Department::query()->whereKey($id)->exists()) {
                    $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'department_not_found'];

                    continue;
                }

                $memberUserIds = DepartmentMembership::query()
                    ->where('department_id', $id)
                    ->where('is_active', true)
                    ->pluck('user_id')
                    ->map(static fn (int $userId): int => $userId)
                    ->all();

                foreach ($memberUserIds as $memberUserId) {
                    $resolvedRecipients[] = $this->userRecipient($memberUserId);
                }

                continue;
            }

            $unresolved[] = ['type' => $type, 'id' => $id, 'reason' => 'unsupported_target_type'];
        }

        return [
            'resolved_recipients' => collect($resolvedRecipients)
                ->unique('key')
                ->values()
                ->all(),
            'unresolved' => $unresolved,
        ];
    }

    /** @return array{key:string,user_id:int,email:null,campus_id:int|null} */
    private function userRecipient(int $userId, ?int $campusId = null): array
    {
        return [
            'key' => 'user:'.$userId,
            'user_id' => $userId,
            'email' => null,
            'campus_id' => $campusId,
        ];
    }

    /** @return array{key:string,user_id:null,email:string,campus_id:null} */
    private function externalEmailRecipient(string $email): array
    {
        return [
            'key' => 'email:'.$email,
            'user_id' => null,
            'email' => $email,
            'campus_id' => null,
        ];
    }
}
