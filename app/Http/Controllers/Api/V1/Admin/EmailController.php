<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Campus;
use App\Models\EmailLog;
use App\Models\Role;
use App\Services\EmailService;
use App\Services\NotificationService;
use App\Shared\Contracts\Notification\ExternalEmailNotification;
use App\Shared\Contracts\Notification\ExternalEmailPublisher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EmailController extends Controller
{
    public function __construct(
        protected EmailService $emailService,
        protected NotificationService $notificationService,
        protected ExternalEmailPublisher $externalEmailPublisher,
    ) {}

    /**
     * Send a single email.
     */
    public function sendSingle(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'recipient' => 'required|email',
                'subject' => 'required|string|max:255',
                'content' => 'required|string',
                'template_variables' => 'nullable|array',
                'attachments' => 'prohibited',
            ]);

            $eventId = $this->externalEmailPublisher->publishAfterCommit(new ExternalEmailNotification(
                recipientEmails: [$validated['recipient']],
                subject: $this->replaceVariables($validated['subject'], $validated['template_variables'] ?? []),
                html: $this->replaceVariables($validated['content'], $validated['template_variables'] ?? []),
                campusId: session('current_campus_id') ? (int) session('current_campus_id') : null,
                actorUserId: Auth::id(),
                aggregateType: 'admin_single_email',
                typeKey: 'admin_single_email',
            ));

            return response()->json([
                'success' => true,
                'message' => 'Email queued for sending',
                'data' => [
                    'email_log_id' => null,
                    'status' => 'queued',
                    'event_id' => $eventId,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Send bulk emails.
     */
    public function sendBulk(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'recipients' => 'required|array|min:1',
                'recipients.*' => 'email',
                'subject' => 'required|string|max:255',
                'content' => 'required|string',
                'template_variables' => 'nullable|array',
                'template_variables_per_recipient' => 'nullable|array',
                'attachments' => 'prohibited',
                'chunk_size' => 'nullable|integer|min:10|max:500',
            ]);

            $batchId = (string) Str::uuid();
            $eventIds = [];
            foreach ($validated['recipients'] as $recipient) {
                $variables = array_merge(
                    $validated['template_variables'] ?? [],
                    $validated['template_variables_per_recipient'][$recipient] ?? [],
                );
                $eventIds[] = $this->externalEmailPublisher->publishAfterCommit(new ExternalEmailNotification(
                    recipientEmails: [$recipient],
                    subject: $this->replaceVariables($validated['subject'], $variables),
                    html: $this->replaceVariables($validated['content'], $variables),
                    campusId: session('current_campus_id') ? (int) session('current_campus_id') : null,
                    actorUserId: Auth::id(),
                    aggregateType: 'admin_bulk_email',
                    aggregateId: $batchId,
                    typeKey: 'admin_bulk_email',
                    deduplicationKey: 'admin_bulk_email:'.$batchId.':'.mb_strtolower($recipient),
                ));
            }

            $result = [
                'batch_id' => $batchId,
                'laravel_batch_id' => null,
                'total_recipients' => count($validated['recipients']),
                'invalid_recipients' => 0,
                'invalid_emails' => [],
                'chunks' => count($eventIds),
                'event_ids' => $eventIds,
                'estimated_completion' => null,
            ];

            return response()->json([
                'success' => true,
                'message' => 'Bulk email batch queued for sending',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send bulk emails',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /** @param array<string, mixed> $variables */
    private function replaceVariables(string $content, array $variables): string
    {
        $replacements = [];
        foreach ($variables as $key => $value) {
            if (is_scalar($value) || $value instanceof \Stringable) {
                $replacements['{{'.$key.'}}'] = (string) $value;
            }
        }

        return strtr($content, $replacements);
    }

    /**
     * Send notification to users.
     */
    public function sendNotification(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'event_type' => 'required|string',
                'recipients' => 'required|array|min:1',
                'recipients.*' => 'email',
                'data' => 'nullable|array',
                'check_preferences' => 'nullable|boolean',
            ]);

            $result = $this->notificationService->sendAcademicNotification(
                $validated['event_type'],
                $validated['recipients'],
                $validated['data'] ?? [],
                $validated['check_preferences'] ?? true
            );

            return response()->json([
                'success' => $result['success'],
                'message' => 'Notification processing completed',
                'data' => $result,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Schedule a reminder notification.
     */
    public function scheduleReminder(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'type' => 'required|string',
                'recipients' => 'required|array|min:1',
                'recipients.*' => 'email',
                'schedule_time' => 'required|date|after:now',
                'data' => 'nullable|array',
            ]);

            $scheduleTime = Carbon::parse($validated['schedule_time']);

            $this->notificationService->scheduleReminder(
                $validated['type'],
                $validated['recipients'],
                $scheduleTime,
                $validated['data'] ?? []
            );

            return response()->json([
                'success' => true,
                'message' => 'Reminder scheduled successfully',
                'data' => [
                    'scheduled_for' => $scheduleTime->toISOString(),
                    'recipients_count' => count($validated['recipients']),
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule reminder',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get email logs.
     */
    public function logs(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'status' => 'nullable|string',
                'recipient' => 'nullable|string',
                'batch_id' => 'nullable|string',
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
                'per_page' => 'nullable|integer|min:10|max:100',
            ]);

            $logs = $this->emailService->getEmailLogs($validated);

            return response()->json([
                'success' => true,
                'data' => $logs,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve email logs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get email statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date',
                'end_date' => 'nullable|date|after_or_equal:start_date',
            ]);

            $startDate = $validated['start_date'] ? Carbon::parse($validated['start_date']) : null;
            $endDate = $validated['end_date'] ? Carbon::parse($validated['end_date']) : null;

            $statistics = $this->emailService->getEmailStatistics($startDate, $endDate);

            return response()->json([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve email statistics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get email log details.
     */
    public function showLog(EmailLog $emailLog): JsonResponse
    {
        $emailLog->load(['template', 'user']);

        return response()->json([
            'success' => true,
            'data' => $emailLog,
        ]);
    }

    /**
     * Retry failed email.
     */
    public function retryEmail(EmailLog $emailLog): JsonResponse
    {
        try {
            if (! $emailLog->canRetry()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Email cannot be retried',
                ], 400);
            }

            // Reset status and dispatch job again
            $emailLog->update(['status' => EmailLog::STATUS_PENDING]);

            // We need to retrieve the original content - for simplicity, we'll send a basic retry
            $this->emailService->sendSingleEmail(
                $emailLog->recipient,
                $emailLog->subject,
                'Retry: '.$emailLog->subject,
                $emailLog->template,
                [],
                Auth::user()
            );

            return response()->json([
                'success' => true,
                'message' => 'Email queued for retry',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry email',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get user roles for bulk email recipient selection.
     */
    public function getUserRoles(): JsonResponse
    {
        try {
            // This would typically come from a RoleService or similar
            $roles = Role::withCount('users')->get();

            return response()->json([
                'success' => true,
                'data' => $roles,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user roles',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get campuses for bulk email recipient selection.
     */
    public function getCampuses(): JsonResponse
    {
        try {
            // This would typically come from a CampusService or similar
            $campuses = Campus::withCount('users')->get();

            return response()->json([
                'success' => true,
                'data' => $campuses,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve campuses',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
