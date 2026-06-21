<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Modules\AI\Models\AiAgentTrace;
use App\Modules\AI\Models\AiMessage;
use App\Modules\AI\Models\AiProviderUsage;

class AiProviderUsageRecorder
{
    public function __construct(private readonly AiRedactor $redactor) {}

    public function record(AiAgentTrace $trace, array $data): AiProviderUsage
    {
        $message = $data['message'] ?? null;
        $this->redactor->redact($data);

        return AiProviderUsage::query()->create([
            'ai_conversation_id' => $trace->ai_conversation_id,
            'ai_agent_trace_id' => $trace->id,
            'ai_message_id' => $message instanceof AiMessage ? $message->id : $trace->ai_message_id,
            'provider' => (string) ($data['provider'] ?? $trace->provider),
            'model' => (string) ($data['model'] ?? $trace->model),
            'status' => (string) ($data['status'] ?? 'unknown'),
            'tokens_in' => $this->nullableInt($data['tokens_in'] ?? null),
            'tokens_out' => $this->nullableInt($data['tokens_out'] ?? null),
            'estimated_cost_minor' => $this->nullableInt($data['estimated_cost_minor'] ?? null),
            'duration_ms' => $this->nullableInt($data['duration_ms'] ?? null),
            'safe_error_code' => $this->nullableString($data['safe_error_code'] ?? null),
            'provider_request_id' => $this->nullableString($data['provider_request_id'] ?? null),
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
