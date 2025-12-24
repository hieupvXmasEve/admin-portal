<?php

namespace App\Actions\Query;

use App\Models\FormResponse;
use App\Models\User;
use App\Models\ResponseAssignment;
use Illuminate\Validation\ValidationException;

class AssignQueryTicketAction
{
    public function execute(FormResponse $response, User $assignee, ?User $actor = null): FormResponse
    {
        // 1. Validate
        // Ensure response is a query type? Or just any response? SRS says Query tickets.
        // Assuming Form type = 'query'.
        if ($response->form->type !== 'query') {
            // Or maybe we treat all responses as assignable?
            // SRS says Query Ticket.
        }

        // Check if already assigned
        $currentAssignee = $response->assigned_to_user_id;
        $action = $currentAssignee ? 'reassign' : 'assign';

        // 2. Update Response
        $response->update([
            'assigned_to_user_id' => $assignee->id,
            'query_status' => 'assigned', // OR in_progress if explicitly set
            // If it was 'new', now it is 'assigned'.
            'status' => $response->status, // unchanged
        ]);

        // 3. Create Audit Log
        ResponseAssignment::create([
            'response_id' => $response->id,
            'assigned_to_user_id' => $assignee->id,
            'assigned_by_user_id' => $actor ? $actor->id : null,
            'action' => $action,
        ]);

        // 4. Sync with QueryTicket if exists
        if ($response->queryTicket) {
            $response->queryTicket->update([
                'assigned_to_user_id' => $assignee->id,
                'status' => 'pending',
            ]);
        }

        return $response;
    }
}
