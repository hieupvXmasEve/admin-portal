<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiResponse;
use App\Models\EmailLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailLogController extends Controller
{
    public function index(Request $request)
    {
        // Get filter parameters from request
        $status = $request->get('status');
        $recipient = $request->get('recipient');
        $batchId = $request->get('batch_id');
        $perPage = (int) $request->get('per_page', 15);

        // Validate per_page to prevent abuse
        $perPage = min(max($perPage, 5), 100);

        // Build query with eager loading for performance
        $query = EmailLog::query()
            ->with(['template:id,name,type', 'user:id,name,email'])
            ->latest('created_at');

        // Apply filters
        if ($status && $status !== 'all') {
            $query->where('status', $status);
        }

        if ($recipient && $recipient !== '') {
            $query->where('recipient', 'like', '%' . $recipient . '%');
        }

        if ($batchId && $batchId !== '') {
            $query->where('batch_id', $batchId);
        }

        // Get paginated results
        $emailLogs = $query->paginate($perPage);

        // Transform the data to include only necessary fields and format dates
        $emailLogs->getCollection()->transform(function ($log) {
            return [
                'id' => $log->id,
                'recipient' => $log->recipient,
                'sender' => $log->sender,
                'subject' => $log->subject,
                'status' => $log->status,
                'retry_count' => $log->retry_count,
                'error_message' => $log->error_message,
                'created_at' => $log->created_at->toISOString(),
                'updated_at' => $log->updated_at->toISOString(),
                'template' => $log->template ? [
                    'id' => $log->template->id,
                    'name' => $log->template->name,
                    'type' => $log->template->type,
                ] : null,
                'user' => $log->user ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ] : null,
                // Add computed properties
                'can_retry' => $log->status === EmailLog::STATUS_FAILED && $log->retry_count < 3,
                'batch_id' => $log->batch_id,
                'queued_at' => $log->queued_at?->toISOString(),
                'sent_at' => $log->sent_at?->toISOString(),
                'delivered_at' => $log->delivered_at?->toISOString(),
                'failed_at' => $log->failed_at?->toISOString(),
            ];
        });

        // Get available status options for filter dropdown
        $statusOptions = [
            ['value' => 'all', 'label' => 'All Status'],
            ['value' => EmailLog::STATUS_PENDING, 'label' => 'Pending'],
            ['value' => EmailLog::STATUS_QUEUED, 'label' => 'Queued'],
            ['value' => EmailLog::STATUS_SENDING, 'label' => 'Sending'],
            ['value' => EmailLog::STATUS_SENT, 'label' => 'Sent'],
            ['value' => EmailLog::STATUS_DELIVERED, 'label' => 'Delivered'],
            ['value' => EmailLog::STATUS_FAILED, 'label' => 'Failed'],
            ['value' => EmailLog::STATUS_BOUNCED, 'label' => 'Bounced'],
            ['value' => EmailLog::STATUS_REJECTED, 'label' => 'Rejected'],
        ];

        return Inertia::render('Admin/EmailLog/Index', [
            'emailLogs' => $emailLogs,
            'filters' => [
                'status' => $status,
                'recipient' => $recipient,
                'batch_id' => $batchId,
                'per_page' => $perPage,
            ],
            'statusOptions' => $statusOptions,
        ]);
    }

    // API routes
    // Show email log
    public function show(EmailLog $emailLog): JsonResponse
    {
        return ApiResponse::success(
            data: $emailLog,
            message: 'Email log retrieved successfully'
        );
    }
}
