<?php

namespace App\Actions\Query;

use App\Models\FormResponse;
use App\Models\QueryReply;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReplyToQueryAction
{
    public function execute(
        FormResponse $response,
        User $author,
        string $message,
        bool $isOfficial = true,
        ?int $uploadRecordId = null,
        ?bool $setPending = false
    ): QueryReply {
        return DB::transaction(function () use ($response, $author, $message, $isOfficial, $uploadRecordId, $setPending) {
            // 1. Ensure a ticket exists for this response if it doesn't already
            $ticket = $response->queryTicket;

            if (!$ticket) {
                // If it's a query type form, it should have a ticket record.
                $ticket = $response->queryTicket()->create([
                    'status' => 'open',
                    'priority' => 'normal',
                ]);
            }

            // 2. Create the reply
            $reply = QueryReply::create([
                'ticket_id' => $ticket->id,
                'author_user_id' => $author->id,
                'message' => $message,
                'is_official_answer' => $isOfficial,
                'upload_record_id' => $uploadRecordId,
            ]);

            // 3. Update status in both ticket and response
            $newStatus = $isOfficial ? 'answered' : ($setPending ? 'pending' : 'open');
            
            $ticket->update(['status' => $newStatus]);
            $response->update(['query_status' => $newStatus]);

            return $reply;
        });
    }
}
