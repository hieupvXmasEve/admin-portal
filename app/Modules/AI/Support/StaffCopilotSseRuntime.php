<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiRunEvent;
use App\Modules\AI\Models\AiToolCall;
use Generator;
use Illuminate\Http\StreamedEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class StaffCopilotSseRuntime
{
    public function __construct(
        private readonly AiAuditRecorder $auditRecorder,
        private readonly AiRedactor $redactor,
        private readonly MetricCatalog $catalog,
        private readonly StaffCopilotAgentRunner $runner,
    ) {}

    public function queue(User $actor, ?Campus $campus, string $question, ?int $conversationId = null): AiChatRun
    {
        return DB::transaction(function () use ($actor, $campus, $question, $conversationId): AiChatRun {
            $runContract = $this->defaultRunContract();
            $conversation = $this->conversationFor($actor, $campus, $question, $conversationId);
            $userMessage = $this->auditRecorder->recordMessage($conversation, 'user', $question, [
                'content_classification' => 'staff_copilot_question',
            ]);
            $assistantMessage = $this->auditRecorder->recordMessage($conversation, 'assistant', '', [
                'content_classification' => 'staff_copilot_answer',
            ]);
            $trace = $this->auditRecorder->startTrace($conversation, $userMessage, [
                'provider' => $runContract['provider'],
                'model' => $runContract['model'],
                'prompt_version' => $runContract['prompt_version'],
                'catalog_version' => $runContract['catalog_version'],
                'tool_schema_version' => $runContract['tool_schema_version'],
            ]);

            $run = AiChatRun::query()->create([
                'ai_conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'assistant_message_id' => $assistantMessage->id,
                'ai_agent_trace_id' => $trace->id,
                'user_id' => $actor->id,
                'campus_id' => $campus?->id,
                ...$runContract,
                'status' => AiChatRun::STATUS_QUEUED,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            $this->recordEvent($run, 'run.queued', $this->progressPayload(AiChatRun::STATUS_QUEUED, [
                'provider' => $run->provider,
                'model' => $run->model,
                'runtime_mode' => $run->runtime_mode,
                'stream_transport' => $run->stream_transport,
                'stream_mode' => $run->stream_mode,
                'prompt_version' => $run->prompt_version,
                'catalog_version' => $run->catalog_version,
                'tool_schema_version' => $run->tool_schema_version,
            ]));
            $this->recordEvent($run, 'message.created', [
                'message_id' => $userMessage->id,
                'role' => 'user',
            ], $userMessage);
            $this->recordEvent($run, 'message.created', [
                'message_id' => $assistantMessage->id,
                'role' => 'assistant',
                'status' => AiChatRun::STATUS_QUEUED,
            ], $assistantMessage);

            return $run->refresh();
        });
    }

    public function cancel(AiChatRun $run): AiChatRun
    {
        return DB::transaction(function () use ($run): AiChatRun {
            $run = $run->newQuery()->lockForUpdate()->find($run->id) ?? $run;

            if ($run->isTerminal()) {
                return $run;
            }

            $now = now();

            $run->forceFill([
                'status' => AiChatRun::STATUS_CANCELLED,
                'cancellation_requested_at' => $run->cancellation_requested_at ?? $now,
                'completed_at' => $now,
                'safe_error_code' => 'run_cancelled',
            ])->save();

            $assistantMessage = $run->assistantMessage()->first();

            if ($assistantMessage instanceof AiMessage) {
                $assistantMessage->forceFill([
                    'redacted_content' => 'This run was cancelled before completion.',
                    'content_hash' => hash('sha256', 'This run was cancelled before completion.'),
                    'hidden_sections' => ['run_cancelled'],
                ])->save();
            }

            $trace = $run->trace()->first();

            if ($trace) {
                $trace->forceFill([
                    'status' => AiChatRun::STATUS_CANCELLED,
                    'safe_error_code' => 'run_cancelled',
                ])->save();
            }

            $this->recordEvent($run, 'run.cancelled', $this->progressPayload(AiChatRun::STATUS_CANCELLED, [
                'safe_error_code' => 'run_cancelled',
            ]), $assistantMessage);

            return $run->refresh();
        });
    }

    public function retry(AiChatRun $sourceRun): AiChatRun
    {
        return DB::transaction(function () use ($sourceRun): AiChatRun {
            $runContract = $this->defaultRunContract();
            $sourceRun->loadMissing(['conversation', 'userMessage', 'user', 'campus']);

            $conversation = $sourceRun->conversation;
            $userMessage = $sourceRun->userMessage;
            $actor = $sourceRun->user;

            if (! $conversation instanceof AiConversation || ! $userMessage instanceof AiMessage || ! $actor instanceof User) {
                return $this->queueMissingRetry($sourceRun);
            }

            $assistantMessage = $this->auditRecorder->recordMessage($conversation, 'assistant', '', [
                'content_classification' => 'staff_copilot_answer',
            ]);
            $trace = $this->auditRecorder->startTrace($conversation, $userMessage, [
                'provider' => $runContract['provider'],
                'model' => $runContract['model'],
                'prompt_version' => $runContract['prompt_version'],
                'catalog_version' => $runContract['catalog_version'],
                'tool_schema_version' => $runContract['tool_schema_version'],
            ]);

            $run = AiChatRun::query()->create([
                'ai_conversation_id' => $conversation->id,
                'user_message_id' => $userMessage->id,
                'assistant_message_id' => $assistantMessage->id,
                'ai_agent_trace_id' => $trace->id,
                'user_id' => $actor->id,
                'campus_id' => $sourceRun->campus_id,
                ...$runContract,
                'status' => AiChatRun::STATUS_QUEUED,
                'idempotency_key' => (string) Str::uuid(),
            ]);

            $this->recordEvent($run, 'run.queued', $this->progressPayload(AiChatRun::STATUS_QUEUED, [
                'retry_of_run_id' => $sourceRun->id,
                'provider' => $run->provider,
                'model' => $run->model,
                'runtime_mode' => $run->runtime_mode,
                'stream_transport' => $run->stream_transport,
                'stream_mode' => $run->stream_mode,
                'prompt_version' => $run->prompt_version,
                'catalog_version' => $run->catalog_version,
                'tool_schema_version' => $run->tool_schema_version,
            ]));
            $this->recordEvent($run, 'message.created', [
                'message_id' => $assistantMessage->id,
                'role' => 'assistant',
                'status' => AiChatRun::STATUS_QUEUED,
                'retry_of_run_id' => $sourceRun->id,
            ], $assistantMessage);

            return $run->refresh();
        });
    }

    public function validateCursor(AiChatRun $run, int $cursor): bool
    {
        if ($cursor < 0) {
            return false;
        }

        if ($cursor === 0) {
            return true;
        }

        return AiRunEvent::query()
            ->where('ai_chat_run_id', $run->id)
            ->whereKey($cursor)
            ->exists();
    }

    public function stream(AiChatRun $run, int $cursor = 0): Generator
    {
        $lastEventId = $cursor;

        foreach ($this->eventsAfter($run, $lastEventId) as $event) {
            $lastEventId = $event->id;

            yield $this->streamedEvent($event);
        }

        $run->refresh();

        if (! $run->isTerminal()) {
            $this->execute($run);
        }

        foreach ($this->eventsAfter($run, $lastEventId) as $event) {
            yield $this->streamedEvent($event);
        }
    }

    private function execute(AiChatRun $run): void
    {
        $run->loadMissing(['user', 'campus', 'userMessage', 'assistantMessage', 'trace']);

        if ($run->isTerminal()) {
            return;
        }

        if ($run->cancellation_requested_at !== null) {
            $this->cancel($run);

            return;
        }

        if (! $run->user instanceof User || ! $run->userMessage instanceof AiMessage || ! $run->assistantMessage instanceof AiMessage || ! $run->trace) {
            $this->failRun($run, 'run_context_missing');

            return;
        }

        $started = microtime(true);
        $finalAnswerId = 'staff-copilot-'.$run->trace->id;

        try {
            $this->markStatus($run, AiChatRun::STATUS_RUNNING, 'run.started');
            $this->markStatus($run, AiChatRun::STATUS_PLANNING, 'run.status');

            $answer = $this->runner->run($run->userMessage->redacted_content, $run->user, $run->campus, $run->trace);
            $run->trace->refresh();

            if ($run->trace->safe_error_code === 'provider_invocation_failed') {
                $this->recordEvent($run, 'provider.failed', [
                    'safe_error_code' => 'provider_invocation_failed',
                    'provider' => $run->trace->provider,
                    'model' => $run->trace->model,
                    'progress_label' => 'Provider unavailable',
                    'progress_detail' => 'Swinx is falling back safely.',
                ]);
            }

            if ($run->trace->toolCalls()->exists()) {
                $this->markStatus($run, AiChatRun::STATUS_TOOL_RUNNING, 'run.status');
            }

            $this->recordToolEvents($run);

            $durationMs = max(0, (int) round((microtime(true) - $started) * 1000));
            $safeErrorCode = $answer->safeErrorCode() ?? $run->trace->safe_error_code;

            $run->assistantMessage->forceFill([
                'redacted_content' => $this->redactor->redactText($answer->content()),
                'content_hash' => hash('sha256', $answer->content()),
                'final_answer_id' => $finalAnswerId,
                'hidden_sections' => $this->redactor->redact($answer->hiddenSections()),
            ])->save();

            $run->trace->forceFill([
                'status' => $answer->status(),
                'step_count' => $answer->toolExecuted() ? max(1, $run->trace->toolCalls()->count()) : 0,
                'duration_ms' => $durationMs,
                'safe_error_code' => $safeErrorCode,
                'final_answer_id' => $finalAnswerId,
            ])->save();

            $run->forceFill([
                'provider' => $run->trace->provider,
                'model' => $run->trace->model,
                'prompt_version' => $run->trace->prompt_version,
                'catalog_version' => $run->trace->catalog_version,
                'tool_schema_version' => $run->trace->tool_schema_version,
                'runtime_mode' => $run->trace->provider === 'deterministic' ? 'deterministic' : 'live_provider',
                'status' => AiChatRun::STATUS_STREAMING,
                'duration_ms' => $durationMs,
                'safe_error_code' => $safeErrorCode,
            ])->save();

            $this->recordEvent($run, 'run.status', $this->progressPayload(AiChatRun::STATUS_STREAMING, [
                'provider' => $run->provider,
                'model' => $run->model,
            ]));

            $this->recordEvent($run, 'message.delta', [
                'message_id' => $run->assistant_message_id,
                'delta' => $answer->content(),
            ], $run->assistantMessage);

            $this->recordEvent($run, 'message.completed', [
                'message_id' => $run->assistant_message_id,
                'status' => $answer->status(),
                'safe_error_code' => $safeErrorCode,
                'source_references' => $answer->payload()['source_references'] ?? [],
                'progress_label' => $answer->isSuccessful() ? 'Answer ready' : 'Could not complete',
            ], $run->assistantMessage);

            $terminalStatus = $answer->isSuccessful() ? AiChatRun::STATUS_COMPLETED : AiChatRun::STATUS_FAILED;
            $terminalEvent = $terminalStatus === AiChatRun::STATUS_COMPLETED ? 'run.completed' : 'run.failed';

            $run->forceFill([
                'status' => $terminalStatus,
                'completed_at' => $terminalStatus === AiChatRun::STATUS_COMPLETED ? now() : null,
                'failed_at' => $terminalStatus === AiChatRun::STATUS_FAILED ? now() : null,
                'duration_ms' => $durationMs,
                'safe_error_code' => $safeErrorCode,
            ])->save();

            $this->recordEvent($run, $terminalEvent, $this->progressPayload($terminalStatus, [
                'safe_error_code' => $safeErrorCode,
            ]));
        } catch (Throwable) {
            $this->failRun($run, 'runtime_execution_failed');
        }
    }

    private function failRun(AiChatRun $run, string $safeErrorCode): void
    {
        DB::transaction(function () use ($run, $safeErrorCode): void {
            $run->forceFill([
                'status' => AiChatRun::STATUS_FAILED,
                'failed_at' => now(),
                'safe_error_code' => $safeErrorCode,
            ])->save();

            $assistantMessage = $run->assistantMessage()->first();

            if ($assistantMessage instanceof AiMessage) {
                $assistantMessage->forceFill([
                    'redacted_content' => 'The AI copilot run could not complete safely.',
                    'content_hash' => hash('sha256', 'The AI copilot run could not complete safely.'),
                    'hidden_sections' => [$safeErrorCode],
                ])->save();
            }

            $trace = $run->trace()->first();

            if ($trace) {
                $trace->forceFill([
                    'status' => AiChatRun::STATUS_FAILED,
                    'safe_error_code' => $safeErrorCode,
                ])->save();
            }

            $this->recordEvent($run, 'run.failed', $this->progressPayload(AiChatRun::STATUS_FAILED, [
                'safe_error_code' => $safeErrorCode,
            ]), $assistantMessage);
        });
    }

    private function queueMissingRetry(AiChatRun $sourceRun): AiChatRun
    {
        $sourceRun->forceFill([
            'status' => AiChatRun::STATUS_FAILED,
            'safe_error_code' => 'run_retry_context_missing',
            'failed_at' => now(),
        ])->save();

        return $sourceRun;
    }

    private function markStatus(AiChatRun $run, string $status, string $eventType, array $payload = []): void
    {
        $attributes = ['status' => $status];

        if ($status === AiChatRun::STATUS_RUNNING && $run->started_at === null) {
            $attributes['started_at'] = now();
        }

        $run->forceFill($attributes)->save();
        $this->recordEvent($run, $eventType, $this->progressPayload($status, $payload));
    }

    /**
     * @return array{provider: string, model: string, prompt_version: string, catalog_version: string, tool_schema_version: string, runtime_mode: string, stream_transport: string, stream_mode: string}
     */
    private function defaultRunContract(): array
    {
        return [
            'provider' => 'deterministic',
            'model' => 'staff-copilot-mvp',
            'prompt_version' => StaffCopilotAgentRunner::PROMPT_VERSION,
            'catalog_version' => $this->catalog->version(),
            'tool_schema_version' => $this->catalog->toolSchemaVersion(),
            'runtime_mode' => 'deterministic',
            'stream_transport' => 'sse',
            'stream_mode' => 'fallback_snapshot',
        ];
    }

    private function recordToolEvents(AiChatRun $run): void
    {
        $toolCalls = $run->trace->toolCalls()->orderBy('id')->get();

        foreach ($toolCalls as $toolCall) {
            $this->recordEvent($run, 'tool.started', $this->toolProgressPayload($toolCall, 'started'), null, $toolCall);

            $eventType = match ($toolCall->status) {
                'completed', 'partial' => 'tool.completed',
                'denied' => 'tool.denied',
                default => 'tool.failed',
            };

            $this->recordEvent($run, $eventType, $this->toolProgressPayload($toolCall, $toolCall->status, [
                'status' => $toolCall->status,
                'safe_error_code' => $toolCall->safe_error_code,
            ]), null, $toolCall);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function progressPayload(string $status, array $payload = []): array
    {
        return array_replace([
            'status' => $status,
            'progress_label' => $this->statusLabel($status),
            'progress_detail' => $this->statusDetail($status),
        ], $payload);
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            AiChatRun::STATUS_QUEUED => 'Waiting to start',
            AiChatRun::STATUS_RUNNING => 'Starting',
            AiChatRun::STATUS_PLANNING => 'Reviewing question',
            AiChatRun::STATUS_TOOL_RUNNING => 'Checking approved data',
            AiChatRun::STATUS_STREAMING => 'Writing answer',
            AiChatRun::STATUS_COMPLETED => 'Answer ready',
            AiChatRun::STATUS_FAILED => 'Could not complete',
            AiChatRun::STATUS_CANCELLED => 'Stopped',
            default => 'Working',
        };
    }

    private function statusDetail(string $status): string
    {
        return match ($status) {
            AiChatRun::STATUS_QUEUED => 'Your question is queued.',
            AiChatRun::STATUS_RUNNING => 'Swinx is preparing the run.',
            AiChatRun::STATUS_PLANNING => 'Swinx is deciding what allowed data is needed.',
            AiChatRun::STATUS_TOOL_RUNNING => 'Swinx is reading approved data for this campus.',
            AiChatRun::STATUS_STREAMING => 'The answer is being prepared.',
            AiChatRun::STATUS_COMPLETED => 'The run completed.',
            AiChatRun::STATUS_FAILED => 'The run stopped safely.',
            AiChatRun::STATUS_CANCELLED => 'The run was cancelled.',
            default => 'The run is in progress.',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function toolProgressPayload(AiToolCall $toolCall, string $status, array $payload = []): array
    {
        return array_replace([
            'status' => $status,
            'data_group' => $this->dataGroupLabel($toolCall),
            'progress_label' => $this->toolStatusLabel($status),
            'progress_detail' => $this->toolStatusDetail($toolCall, $status),
        ], $payload);
    }

    private function toolStatusLabel(string $status): string
    {
        return match ($status) {
            'started' => 'Checking approved data',
            'completed' => 'Data check complete',
            'partial' => 'Partial data available',
            'denied' => 'Data access blocked',
            default => 'Data check stopped',
        };
    }

    private function toolStatusDetail(AiToolCall $toolCall, string $status): string
    {
        $dataGroup = $this->dataGroupLabel($toolCall);

        return match ($status) {
            'started' => "Swinx is checking {$dataGroup}.",
            'completed' => "{$dataGroup} is ready for the answer.",
            'partial' => "{$dataGroup} returned partial results.",
            'denied' => "You do not have access to {$dataGroup}.",
            default => "Swinx could not use {$dataGroup}.",
        };
    }

    private function dataGroupLabel(AiToolCall $toolCall): string
    {
        $sourceReport = (string) ($toolCall->source_references[0]['source_report'] ?? '');

        if (str_starts_with($sourceReport, 'finance.')) {
            return 'Finance data';
        }

        if (str_starts_with($sourceReport, 'academic.student-profile.')) {
            return 'Student profile data';
        }

        if (str_starts_with($sourceReport, 'academic.entity-search.')) {
            return 'Directory data';
        }

        if (str_starts_with($sourceReport, 'academic.')) {
            return 'Academic data';
        }

        return 'approved data';
    }

    private function recordEvent(
        AiChatRun $run,
        string $eventType,
        array $payload,
        ?AiMessage $message = null,
        ?AiToolCall $toolCall = null,
    ): AiRunEvent {
        $sequence = ((int) AiRunEvent::query()
            ->where('ai_chat_run_id', $run->id)
            ->max('sequence')) + 1;

        $event = AiRunEvent::query()->create([
            'ai_chat_run_id' => $run->id,
            'ai_conversation_id' => $run->ai_conversation_id,
            'ai_message_id' => $message?->id,
            'ai_tool_call_id' => $toolCall?->id,
            'event_type' => $eventType,
            'sequence' => $sequence,
            'redacted_payload' => $this->redactor->redact(array_replace([
                'run_id' => $run->id,
                'event_id' => null,
                'sequence' => $sequence,
            ], $payload)),
        ]);

        $event->forceFill([
            'redacted_payload' => array_replace($event->redacted_payload ?? [], [
                'event_id' => $event->id,
            ]),
        ])->save();

        $run->forceFill(['last_event_id' => $event->id])->save();

        return $event;
    }

    /**
     * @return iterable<AiRunEvent>
     */
    private function eventsAfter(AiChatRun $run, int $cursor): iterable
    {
        return AiRunEvent::query()
            ->where('ai_chat_run_id', $run->id)
            ->where('id', '>', $cursor)
            ->orderBy('id')
            ->get();
    }

    private function streamedEvent(AiRunEvent $event): StreamedEvent
    {
        return new StreamedEvent(
            event: $event->event_type,
            data: $event->redacted_payload ?? [],
        );
    }

    private function conversationFor(User $actor, ?Campus $campus, string $question, ?int $conversationId): AiConversation
    {
        $query = AiConversation::query()
            ->where('user_id', $actor->id)
            ->where('origin', 'staff_chat')
            ->where('status', 'open')
            ->when(
                $campus !== null,
                fn ($builder) => $builder->where('campus_id', $campus->id),
                fn ($builder) => $builder->whereNull('campus_id'),
            );

        if ($conversationId !== null) {
            $conversation = (clone $query)->whereKey($conversationId)->first();

            if ($conversation instanceof AiConversation) {
                return $conversation;
            }
        }

        $conversation = (clone $query)
            ->latest('last_message_at')
            ->latest('id')
            ->first();

        if ($conversation instanceof AiConversation) {
            return $conversation;
        }

        return $this->auditRecorder->startConversation($actor, $campus, [
            'origin' => 'staff_chat',
            'title' => Str::limit($question, 80, ''),
            'actor_role_snapshot' => [
                'user_id' => $actor->id,
                'role' => 'staff',
                'permissions' => ['view_ai_metrics'],
            ],
            'campus_scope_snapshot' => [
                'campus_ids' => $campus ? [$campus->id] : [],
            ],
        ]);
    }
}
