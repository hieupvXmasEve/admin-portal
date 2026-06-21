<?php

declare(strict_types=1);

use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiEvaluationCase;
use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\AiEvaluationRunner;
use App\Modules\AI\Support\AiProviderUsageRecorder;
use App\Modules\AI\Support\AiRedactor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('redacts secrets from nested prompt tool and provider payloads', function () {
    $redactor = app(AiRedactor::class);

    $payload = [
        'api_key' => 'sk-live-secret-123',
        'encrypted_api_key' => 'encrypted-secret-material',
        'authorization_header' => 'Bearer provider-token',
        'prompt' => 'Student ABC owes money. Use sk-inline-secret.',
        'filters' => [
            'campus_id' => 10,
            'student_data_without_permission_snapshot' => [
                'full_name' => 'Sensitive Student',
                'national_id' => '001122334455',
            ],
        ],
        'raw_provider_request' => [
            'messages' => [['content' => 'raw prompt']],
        ],
        'raw_provider_response' => [
            'choices' => [['message' => ['content' => 'raw answer']]],
        ],
        'source_references' => [
            [
                'source' => 'finance.dashboard',
                'record_count' => 4,
            ],
        ],
    ];

    $redacted = $redactor->redact($payload);
    $encoded = json_encode($redacted, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($redacted['api_key'])->toBe('[REDACTED]')
        ->and($redacted['encrypted_api_key'])->toBe('[REDACTED]')
        ->and($redacted['authorization_header'])->toBe('[REDACTED]')
        ->and($redacted['raw_provider_request'])->toBe('[REDACTED]')
        ->and($redacted['raw_provider_response'])->toBe('[REDACTED]')
        ->and($redacted['filters']['student_data_without_permission_snapshot'])->toBe('[REDACTED]')
        ->and($encoded)->not->toContain('sk-live-secret-123')
        ->and($encoded)->not->toContain('encrypted-secret-material')
        ->and($encoded)->not->toContain('provider-token')
        ->and($encoded)->not->toContain('sk-inline-secret')
        ->and($encoded)->not->toContain('Sensitive Student')
        ->and($encoded)->toContain('finance.dashboard');
});

it('records correlated AI audit rows with redacted messages tool arguments and provider usage', function () {
    $actor = User::factory()->create(['name' => 'AI Auditor']);
    $campus = Campus::factory()->create();
    $secret = 'sk-audit-secret-456';

    $audit = app(AiAuditRecorder::class);

    $conversation = $audit->startConversation($actor, $campus, [
        'origin' => 'staff_chat',
        'title' => 'Finance check',
        'request_id' => 'req-ai-001',
        'actor_role_snapshot' => ['roles' => ['finance_staff']],
        'status' => 'open',
    ]);

    $message = $audit->recordMessage($conversation, 'user', "Check debt for student ABC using {$secret}", [
        'content_classification' => 'staff_question',
        'hidden_sections' => ['finance.debt'],
    ]);

    $trace = $audit->startTrace($conversation, $message, [
        'provider' => 'openrouter',
        'model' => 'openai/gpt-4o-mini',
        'prompt_version' => 'p-ai-001',
        'catalog_version' => 'catalog-001',
        'tool_schema_version' => 'query_metrics:v1',
        'status' => 'running',
    ]);

    $toolCall = $audit->recordToolCall($trace, [
        'tool_name' => 'query_metrics',
        'tool_schema_version' => 'query_metrics:v1',
        'arguments' => [
            'metric' => 'student_debt',
            'authorization_header' => 'Bearer should-not-store',
            'api_key' => $secret,
            'student_data_without_permission_snapshot' => ['student_code' => 'ABC'],
        ],
        'permission_result' => 'denied',
        'campus_scope_snapshot' => ['campus_ids' => [$campus->id]],
        'hidden_sections' => ['finance.debt'],
        'record_count' => 0,
        'source_references' => [],
        'status' => 'denied',
        'duration_ms' => 7,
        'safe_error_code' => 'forbidden_by_permission',
    ]);

    $usage = app(AiProviderUsageRecorder::class)->record($trace, [
        'message' => $message,
        'provider' => 'openrouter',
        'model' => 'openai/gpt-4o-mini',
        'status' => 'failed',
        'tokens_in' => 25,
        'tokens_out' => 0,
        'estimated_cost_minor' => 1,
        'duration_ms' => 33,
        'safe_error_code' => 'provider_timeout',
        'provider_request_id' => 'provider-req-001',
        'raw_provider_response' => ['body' => $secret],
    ]);

    $messagePayload = json_encode($message->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $toolPayload = json_encode($toolCall->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
    $usagePayload = json_encode($usage->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($conversation->user_id)->toBe($actor->id)
        ->and($conversation->campus_id)->toBe($campus->id)
        ->and($conversation->request_id)->toBe('req-ai-001')
        ->and($message->ai_conversation_id)->toBe($conversation->id)
        ->and($message->content_hash)->not->toBeNull()
        ->and($trace->ai_message_id)->toBe($message->id)
        ->and($toolCall->ai_agent_trace_id)->toBe($trace->id)
        ->and($usage->ai_agent_trace_id)->toBe($trace->id)
        ->and($usage->ai_message_id)->toBe($message->id)
        ->and($messagePayload)->not->toContain($secret)
        ->and($toolPayload)->not->toContain($secret)
        ->and($toolPayload)->not->toContain('Bearer should-not-store')
        ->and($toolPayload)->not->toContain('ABC')
        ->and($usagePayload)->not->toContain($secret)
        ->and($usagePayload)->not->toContain('raw_provider_response');
});

it('records evaluation cases runs and redacted failing result evidence', function () {
    $runner = app(AiEvaluationRunner::class);

    $case = $runner->createCase([
        'key' => 'current_term_defer_count',
        'dataset_version' => 'baseline-v1',
        'question' => 'Kỳ hiện tại có bao nhiêu sinh viên defer?',
        'actor_fixture' => ['role' => 'academic_staff', 'campus' => 'HCM'],
        'permission_context' => ['permissions' => ['view_ai_metrics'], 'campus_ids' => [1]],
        'expected_tool_calls' => [
            ['tool' => 'query_metrics', 'metric' => 'student_defer_count'],
        ],
        'expected_source_references' => [
            ['source' => 'academic.student_actions', 'filter' => 'current_term'],
        ],
        'expected_answer_properties' => [
            'must_cite_sources' => true,
            'must_not_fabricate_numbers' => true,
        ],
        'status' => 'active',
    ]);

    $run = $runner->startRun([
        'dataset_version' => 'baseline-v1',
        'code_version' => 'test-sha',
        'prompt_version' => 'prompt-v1',
        'catalog_version' => 'catalog-v1',
        'tool_schema_version' => 'query_metrics:v1',
        'provider_fake' => 'deterministic',
    ]);

    $result = $runner->recordResult($run, $case, [
        'status' => 'failed',
        'assertions' => [
            'tool_sequence' => false,
            'permission' => true,
            'source_parity' => false,
        ],
        'diff_summary' => 'Expected 3, got 5. Debug token sk-eval-secret.',
        'tool_call_evidence' => [
            'raw_provider_response' => ['content' => 'unsafe'],
            'source_references' => [['source' => 'academic.student_actions']],
        ],
        'source_parity_status' => 'mismatch',
        'safe_error_code' => 'source_parity_mismatch',
        'duration_ms' => 18,
    ]);

    $resultPayload = json_encode($result->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    expect($case)->toBeInstanceOf(AiEvaluationCase::class)
        ->and($run->status)->toBe('running')
        ->and($result->ai_evaluation_run_id)->toBe($run->id)
        ->and($result->ai_evaluation_case_id)->toBe($case->id)
        ->and($result->source_parity_status)->toBe('mismatch')
        ->and($resultPayload)->not->toContain('sk-eval-secret')
        ->and($resultPayload)->not->toContain('raw_provider_response')
        ->and($resultPayload)->toContain('source_parity_mismatch');
});

it('does not create columns for raw provider bodies or unredacted payloads', function () {
    $forbiddenColumns = [
        'api_key',
        'encrypted_api_key',
        'authorization_header',
        'raw_provider_request',
        'raw_provider_response',
        'unredacted_tool_arguments',
        'unredacted_tool_result',
        'unbounded_source_rows',
    ];

    foreach ([
        'ai_conversations',
        'ai_messages',
        'ai_agent_traces',
        'ai_tool_calls',
        'ai_provider_usages',
        'ai_feedback',
        'ai_evaluation_cases',
        'ai_evaluation_runs',
        'ai_evaluation_results',
    ] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();

        foreach ($forbiddenColumns as $column) {
            expect(Schema::hasColumn($table, $column))
                ->toBeFalse();
        }
    }

    expect(DB::getSchemaBuilder()->getColumnListing('ai_tool_calls'))
        ->toContain('redacted_arguments')
        ->toContain('redacted_result_summary');
});
