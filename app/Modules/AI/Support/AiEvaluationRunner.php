<?php

declare(strict_types=1);

namespace App\Modules\AI\Support;

use App\Modules\AI\Models\AiEvaluationCase;
use App\Modules\AI\Models\AiEvaluationResult;
use App\Modules\AI\Models\AiEvaluationRun;

class AiEvaluationRunner
{
    public function __construct(private readonly AiRedactor $redactor) {}

    public function createCase(array $data): AiEvaluationCase
    {
        return AiEvaluationCase::query()->create([
            'key' => (string) $data['key'],
            'dataset_version' => (string) $data['dataset_version'],
            'question' => $this->redactor->redactText((string) $data['question']),
            'actor_fixture' => $this->redactor->redact($data['actor_fixture'] ?? []),
            'permission_context' => $this->redactor->redact($data['permission_context'] ?? []),
            'expected_tool_calls' => $this->redactor->redact($data['expected_tool_calls'] ?? []),
            'expected_source_references' => $this->redactor->redact($data['expected_source_references'] ?? []),
            'expected_answer_properties' => $this->redactor->redact($data['expected_answer_properties'] ?? []),
            'status' => (string) ($data['status'] ?? 'active'),
        ]);
    }

    public function startRun(array $data): AiEvaluationRun
    {
        return AiEvaluationRun::query()->create([
            'dataset_version' => (string) $data['dataset_version'],
            'code_version' => $this->nullableString($data['code_version'] ?? null),
            'prompt_version' => $this->nullableString($data['prompt_version'] ?? null),
            'catalog_version' => $this->nullableString($data['catalog_version'] ?? null),
            'tool_schema_version' => $this->nullableString($data['tool_schema_version'] ?? null),
            'provider_fake' => $this->nullableString($data['provider_fake'] ?? null),
            'status' => (string) ($data['status'] ?? 'running'),
            'started_at' => $data['started_at'] ?? now(),
            'finished_at' => $data['finished_at'] ?? null,
        ]);
    }

    public function recordResult(AiEvaluationRun $run, AiEvaluationCase $case, array $data): AiEvaluationResult
    {
        return AiEvaluationResult::query()->create([
            'ai_evaluation_run_id' => $run->id,
            'ai_evaluation_case_id' => $case->id,
            'status' => (string) $data['status'],
            'assertions' => $this->redactor->redact($data['assertions'] ?? []),
            'redacted_diff_summary' => $this->nullableString(
                $this->redactor->redactText((string) ($data['diff_summary'] ?? ''))
            ),
            'tool_call_evidence' => $this->safeStoragePayload($data['tool_call_evidence'] ?? []),
            'source_parity_status' => $this->nullableString($data['source_parity_status'] ?? null),
            'safe_error_code' => $this->nullableString($data['safe_error_code'] ?? null),
            'duration_ms' => $this->nullableInt($data['duration_ms'] ?? null),
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

    private function safeStoragePayload(mixed $payload): mixed
    {
        if (! is_array($payload)) {
            return $this->redactor->redact($payload);
        }

        $safe = [];

        foreach ($payload as $key => $value) {
            if (is_string($key) && in_array($key, [
                'raw_provider_request',
                'raw_provider_response',
                'unredacted_tool_arguments',
                'unredacted_tool_result',
                'unbounded_source_rows',
            ], true)) {
                continue;
            }

            $safe[$key] = $this->safeStoragePayload($this->redactor->redact($value));
        }

        return $safe;
    }
}
