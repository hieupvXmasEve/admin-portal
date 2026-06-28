<?php

declare(strict_types=1);

namespace App\Modules\AI\Queries;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiChatRun;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiProviderSetting;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\EntityCatalog;
use App\Modules\AI\Support\MetricCatalog;
use App\Modules\AI\Support\StaffMetricQuestionDataset;
use App\Modules\AI\Support\StudentProfileSectionCatalog;
use App\Modules\AI\Support\Tools\ToolRegistry;
use Illuminate\Support\Collection;

class StaffCopilotPageQuery
{
    public function __construct(
        private readonly MetricCatalog $catalog,
        private readonly EntityCatalog $entityCatalog,
        private readonly StudentProfileSectionCatalog $studentProfileSectionCatalog,
        private readonly StaffMetricQuestionDataset $dataset,
        private readonly ToolRegistry $toolRegistry,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function handle(User $actor, ?Campus $campus): array
    {
        $conversation = $this->conversationFor($actor, $campus);

        return [
            'conversation' => $conversation ? $this->conversationPayload($conversation) : null,
            'messages' => $conversation ? $this->messagePayloads($conversation) : [],
            'active_run' => $conversation ? $this->activeRunPayload($conversation) : null,
            'suggested_prompts' => $this->suggestedPrompts(),
            'capabilities' => [
                'tool_names' => $this->toolRegistry->toolNames(),
                'catalog_version' => $this->catalog->version(),
                'entity_catalog_version' => $this->entityCatalog->version(),
                'profile_catalog_version' => $this->studentProfileSectionCatalog->version(),
                'tool_schema_version' => $this->catalog->toolSchemaVersion(),
                'tool_schema_versions' => [
                    'query_metrics' => $this->catalog->toolSchemaVersion(),
                    'search_entities' => $this->entityCatalog->toolSchemaVersion(),
                    'get_entity_profile' => $this->studentProfileSectionCatalog->toolSchemaVersion(),
                ],
                'sdk_installed' => class_exists('Laravel\\Ai\\Enums\\Lab'),
                'live_provider_enabled' => $this->liveProviderEnabled($actor),
                'runtime_mode' => $this->liveProviderEnabled($actor) ? 'live_provider' : 'deterministic',
                'stream_transport' => 'sse',
                'streaming_enabled' => true,
                'websocket_required' => false,
                'supported_provider_stream_modes' => [
                    'openai' => 'sdk_stream_or_fallback',
                    'openrouter' => 'sdk_stream_or_adapter',
                    'anthropic' => 'sdk_stream_or_adapter',
                    'gemini' => 'sdk_stream_or_adapter',
                ],
            ],
        ];
    }

    private function liveProviderEnabled(User $actor): bool
    {
        return AiProviderSetting::query()
            ->where('user_id', $actor->id)
            ->where('enabled', true)
            ->where('last_test_status', 'success')
            ->whereNotNull('encrypted_api_key')
            ->exists();
    }

    private function conversationFor(User $actor, ?Campus $campus): ?AiConversation
    {
        return AiConversation::query()
            ->where('user_id', $actor->id)
            ->where('origin', 'staff_chat')
            ->where('status', 'open')
            ->when(
                $campus !== null,
                fn ($query) => $query->where('campus_id', $campus->id),
                fn ($query) => $query->whereNull('campus_id'),
            )
            ->latest('last_message_at')
            ->latest('id')
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function conversationPayload(AiConversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'status' => $conversation->status,
            'created_at' => $conversation->created_at?->toISOString(),
            'last_message_at' => $conversation->last_message_at?->toISOString(),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function messagePayloads(AiConversation $conversation): array
    {
        $messages = $conversation->messages()
            ->orderBy('id')
            ->get();

        $traces = $this->tracesForMessages($messages);
        $runs = AiChatRun::query()
            ->where('ai_conversation_id', $conversation->id)
            ->whereIn('assistant_message_id', $messages->pluck('id')->all())
            ->get()
            ->keyBy('assistant_message_id');

        return $messages
            ->map(function (AiMessage $message) use ($traces, $runs): array {
                /** @var AiChatRun|null $run */
                $run = $runs->get($message->id);

                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->redacted_content,
                    'created_at' => $message->created_at?->toISOString(),
                    'hidden_sections' => $message->hidden_sections ?? [],
                    'run_id' => $run?->id,
                    'run_status' => $run?->status,
                    'answer' => $message->role === 'assistant'
                        ? $this->answerPayload($traces->get((string) $message->final_answer_id))
                        : null,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function activeRunPayload(AiConversation $conversation): ?array
    {
        $run = AiChatRun::query()
            ->where('ai_conversation_id', $conversation->id)
            ->whereNotIn('status', [
                AiChatRun::STATUS_COMPLETED,
                AiChatRun::STATUS_FAILED,
                AiChatRun::STATUS_CANCELLED,
            ])
            ->latest('id')
            ->first();

        if (! $run instanceof AiChatRun) {
            return null;
        }

        $assistantContent = (string) $run->assistantMessage()
            ->value('redacted_content');

        return [
            'id' => $run->id,
            'status' => $run->status,
            'assistant_message_id' => $run->assistant_message_id,
            'last_event_id' => $run->last_event_id,
            'replay_cursor' => $assistantContent === '' ? 0 : ($run->last_event_id ?? 0),
            'stream_url' => route('ai.copilot.runs.events', $run, false),
            'can_cancel' => true,
            'provider' => $run->provider,
            'model' => $run->model,
            'runtime_mode' => $run->runtime_mode,
            'stream_transport' => $run->stream_transport,
            'stream_mode' => $run->stream_mode,
            'streaming_enabled' => true,
            'websocket_required' => false,
            'prompt_version' => $run->prompt_version,
            'catalog_version' => $run->catalog_version,
            'tool_schema_version' => $run->tool_schema_version,
        ];
    }

    /**
     * @param  Collection<int, AiMessage>  $messages
     * @return Collection<string, AiAgentTrace>
     */
    private function tracesForMessages(Collection $messages): Collection
    {
        $finalAnswerIds = $messages
            ->pluck('final_answer_id')
            ->filter()
            ->map(fn (string $id): string => $id)
            ->values();

        if ($finalAnswerIds->isEmpty()) {
            return collect();
        }

        return AiAgentTrace::query()
            ->with('toolCalls')
            ->whereIn('final_answer_id', $finalAnswerIds->all())
            ->get()
            ->keyBy('final_answer_id');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function answerPayload(?AiAgentTrace $trace): ?array
    {
        if (! $trace) {
            return null;
        }

        /** @var AiToolCall|null $toolCall */
        $toolCall = $trace->toolCalls->sortByDesc('id')->first();

        if (! $toolCall) {
            return [
                'status' => $trace->status,
                'summary' => null,
                'groups' => [],
                'source_references' => [],
                'normalized_filters' => [],
                'group_by' => [],
                'entity_results' => [],
                'entity_types' => [],
                'normalized_query' => null,
                'profile_sections' => [],
                'requested_sections' => [],
                'returned_sections' => [],
                'profile_catalog_version' => null,
                'profile_entity_type' => null,
                'result_limit' => null,
                'campus_scope_snapshot' => [],
                'freshness' => [],
                'warnings' => [],
                'hidden_sections' => [],
                'confidence' => ['level' => 'none', 'basis' => 'not_executed'],
                'safe_error_code' => $trace->safe_error_code,
                'record_count' => 0,
            ];
        }

        $resultSummary = $toolCall->redacted_result_summary ?? [];

        return [
            'status' => $toolCall->status,
            'summary' => $resultSummary['summary'] ?? null,
            'groups' => $resultSummary['groups'] ?? [],
            'source_references' => $toolCall->source_references ?? [],
            'normalized_filters' => $resultSummary['normalized_filters'] ?? [],
            'group_by' => $resultSummary['group_by'] ?? [],
            'entity_results' => $resultSummary['results'] ?? [],
            'entity_types' => $resultSummary['entity_types'] ?? [],
            'normalized_query' => $resultSummary['normalized_query'] ?? null,
            'profile_sections' => $resultSummary['sections'] ?? [],
            'requested_sections' => $resultSummary['requested_sections'] ?? [],
            'returned_sections' => $resultSummary['returned_sections'] ?? [],
            'profile_catalog_version' => $resultSummary['profile_catalog_version'] ?? null,
            'profile_entity_type' => $resultSummary['entity_type'] ?? null,
            'result_limit' => $resultSummary['result_limit'] ?? null,
            'campus_scope_snapshot' => $toolCall->campus_scope_snapshot ?? [],
            'freshness' => $resultSummary['freshness'] ?? [],
            'warnings' => $resultSummary['warnings'] ?? [],
            'hidden_sections' => $toolCall->hidden_sections ?? [],
            'confidence' => $resultSummary['confidence'] ?? ['level' => 'none', 'basis' => 'not_executed'],
            'safe_error_code' => $toolCall->safe_error_code,
            'record_count' => $toolCall->record_count,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suggestedPrompts(): array
    {
        return collect($this->dataset->cases())
            ->map(function (array $case): array {
                $toolCall = $case['expected_tool_calls'][0] ?? [];
                $sourceReference = $case['expected_source_references'][0] ?? [];

                return [
                    'key' => (string) $case['key'],
                    'question' => (string) $case['question'],
                    'metric' => (string) ($toolCall['metric'] ?? ''),
                    'filters' => $toolCall['filters'] ?? [],
                    'group_by' => $toolCall['group_by'] ?? [],
                    'source_report' => (string) ($sourceReference['source_report'] ?? ''),
                ];
            })
            ->values()
            ->all();
    }
}
