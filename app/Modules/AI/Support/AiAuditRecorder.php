<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiConversation;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiToolCall;
use Illuminate\Support\Facades\DB;

class AiAuditRecorder
{
    public function __construct(private readonly AiRedactor $redactor) {}

    public function startConversation(User $actor, ?Campus $campus = null, array $data = []): AiConversation
    {
        return AiConversation::query()->create([
            'user_id' => $actor->id,
            'campus_id' => $campus?->id,
            'origin' => (string) ($data['origin'] ?? 'staff_chat'),
            'title' => $this->nullableString($data['title'] ?? null),
            'status' => (string) ($data['status'] ?? 'open'),
            'request_id' => $this->nullableString($data['request_id'] ?? null),
            'actor_role_snapshot' => $this->redactor->redact($data['actor_role_snapshot'] ?? []),
            'campus_scope_snapshot' => $this->redactor->redact($data['campus_scope_snapshot'] ?? [
                'campus_ids' => $campus ? [$campus->id] : [],
            ]),
            'sdk_conversation_id' => $this->nullableString($data['sdk_conversation_id'] ?? null),
        ]);
    }

    public function recordMessage(
        AiConversation $conversation,
        string $role,
        string $content,
        array $context = [],
    ): AiMessage {
        return DB::transaction(function () use ($conversation, $role, $content, $context): AiMessage {
            $message = AiMessage::query()->create([
                'ai_conversation_id' => $conversation->id,
                'role' => $role,
                'redacted_content' => $this->redactor->redactText($content),
                'content_hash' => hash('sha256', $content),
                'content_classification' => $this->nullableString($context['content_classification'] ?? null),
                'final_answer_id' => $this->nullableString($context['final_answer_id'] ?? null),
                'hidden_sections' => $this->redactor->redact($context['hidden_sections'] ?? []),
            ]);

            $conversation->forceFill(['last_message_at' => now()])->save();

            return $message;
        });
    }

    public function startTrace(AiConversation $conversation, ?AiMessage $message, array $data = []): AiAgentTrace
    {
        return AiAgentTrace::query()->create([
            'ai_conversation_id' => $conversation->id,
            'ai_message_id' => $message?->id,
            'provider' => $this->nullableString($data['provider'] ?? null),
            'model' => $this->nullableString($data['model'] ?? null),
            'prompt_version' => $this->nullableString($data['prompt_version'] ?? null),
            'catalog_version' => $this->nullableString($data['catalog_version'] ?? null),
            'tool_schema_version' => $this->nullableString($data['tool_schema_version'] ?? null),
            'status' => (string) ($data['status'] ?? 'running'),
            'step_count' => (int) ($data['step_count'] ?? 0),
            'duration_ms' => $this->nullableInt($data['duration_ms'] ?? null),
            'safe_error_code' => $this->nullableString($data['safe_error_code'] ?? null),
            'final_answer_id' => $this->nullableString($data['final_answer_id'] ?? null),
        ]);
    }

    public function recordToolCall(AiAgentTrace $trace, array $data): AiToolCall
    {
        return AiToolCall::query()->create([
            'ai_conversation_id' => $trace->ai_conversation_id,
            'ai_agent_trace_id' => $trace->id,
            'tool_name' => (string) $data['tool_name'],
            'tool_schema_version' => $this->nullableString($data['tool_schema_version'] ?? null),
            'redacted_arguments' => $this->redactor->redact($data['arguments'] ?? []),
            'permission_result' => $this->nullableString($data['permission_result'] ?? null),
            'campus_scope_snapshot' => $this->redactor->redact($data['campus_scope_snapshot'] ?? []),
            'hidden_sections' => $this->redactor->redact($data['hidden_sections'] ?? []),
            'record_count' => (int) ($data['record_count'] ?? 0),
            'source_references' => $this->redactor->redact($data['source_references'] ?? []),
            'redacted_result_summary' => $this->redactor->redact($data['result_summary'] ?? null),
            'status' => (string) ($data['status'] ?? 'planned'),
            'duration_ms' => $this->nullableInt($data['duration_ms'] ?? null),
            'safe_error_code' => $this->nullableString($data['safe_error_code'] ?? null),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (int) $value;
    }
}
