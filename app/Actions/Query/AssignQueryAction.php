<?php

namespace App\Actions\Query;

use App\Models\FormResponse;
use App\Models\QueryAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AssignQueryAction
{
    /**
     * @throws ValidationException
     */
    public function execute(FormResponse $response, ?int $toAssigneeUserId, User $actor, ?string $note = null): FormResponse
    {
        // 1. Resolve department_id từ response/form_target.
        $formTarget = $response->formTarget;
        if (!$formTarget || $formTarget->scope_type !== 'department') {
            throw ValidationException::withMessages([
                'response' => 'Only department-scoped queries can be assigned through this workflow.'
            ]);
        }

        $departmentId = (int) $formTarget->scope_id;

        // 2. Assert current user là Head của department.
        if (!$actor->isDepartmentHead($departmentId) && !$actor->hasSystemRole('admin')) {
            throw ValidationException::withMessages([
                'auth' => 'Only department heads or admins can assign tickets.'
            ]);
        }

        // 3. Nếu assign/reassign: assert to_assignee_user_id là member active của same department.
        $toAssignee = null;
        if ($toAssigneeUserId) {
            $toAssignee = User::find($toAssigneeUserId);
            if (!$toAssignee || !$toAssignee->isDepartmentMember($departmentId)) {
                throw ValidationException::withMessages([
                    'assigned_to_user_id' => 'The assignee must be an active member of the department.'
                ]);
            }
        }

        $fromAssigneeUserId = $response->assigned_to_user_id;

        // Determine action type
        if ($fromAssigneeUserId && $toAssigneeUserId) {
            $action = 'reassign';
        } elseif ($toAssigneeUserId) {
            $action = 'assign';
        } else {
            $action = 'unassign';
        }

        return DB::transaction(function () use ($response, $toAssigneeUserId, $actor, $fromAssigneeUserId, $action, $note, $departmentId, $formTarget) {
            // 4. Update:
            $response->update([
                'assigned_to_user_id' => $toAssigneeUserId,
                'query_status' => $toAssigneeUserId ? 'pending' : $response->query_status,
            ]);

            if ($response->queryTicket) {
                $response->queryTicket->update([
                    'assigned_to_user_id' => $toAssigneeUserId,
                    'status' => $toAssigneeUserId ? 'pending' : $response->queryTicket->status,
                ]);
            }

            // 5. Insert record vào query_assignments theo audit rules.
            QueryAssignment::create([
                'query_ticket_id' => $response->queryTicket->id,
                'response_id' => $response->id,
                'department_id' => $departmentId,
                'form_target_id' => $formTarget->id,
                'from_assignee_user_id' => $fromAssigneeUserId,
                'to_assignee_user_id' => $toAssigneeUserId,
                'assigned_by_user_id' => $actor->id,
                'action' => $action,
                'note' => $note,
            ]);

            return $response->fresh(['assignedTo', 'assignments']);
        });
    }
}
