<?php

declare(strict_types=1);

namespace App\Modules\AI\Queries;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\MetricCatalog;
use App\Modules\AI\Support\StaffMetricQuestionDataset;
use App\Modules\AI\Support\Tools\ToolRegistry;
use Illuminate\Support\Collection;

class StaffCopilotPageQuery
{
    public function __construct(
        private readonly MetricCatalog $catalog,
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
            'suggested_prompts' => $this->suggestedPrompts(),
            'capabilities' => [
                'tool_names' => $this->toolRegistry->toolNames(),
                'catalog_version' => $this->catalog->version(),
                'tool_schema_version' => $this->catalog->toolSchemaVersion(),
                'sdk_installed' => class_exists('Laravel\\Ai\\Enums\\Lab'),
                'live_provider_enabled' => false,
            ],
        ];
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

        return $messages
            ->map(fn (AiMessage $message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->redacted_content,
                'created_at' => $message->created_at?->toISOString(),
                'hidden_sections' => $message->hidden_sections ?? [],
                'answer' => $message->role === 'assistant'
                    ? $this->answerPayload($traces->get((string) $message->final_answer_id))
                    : null,
            ])
            ->values()
            ->all();
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
