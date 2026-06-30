<?php

declare(strict_types=1);

use App\Mcp\Support\McpAuditRecorder;
use App\Models\Campus;
use App\Models\User;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\Tools\QueryMetricsResult;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('writes a standalone MCP-channel audit row from a dispatcher result', function () {
    $actor = User::factory()->create();
    $campus = Campus::factory()->create();

    $result = QueryMetricsResult::completed('query_metrics', [
        'tool_schema_version' => 'query_metrics:v1',
        'catalog_version' => 'metric-catalog:v1',
        'metric' => 'finance_collection_summary',
        'normalized_filters' => ['fee_type' => 'tuition_term'],
        'group_by' => ['program'],
        'campus_scope_snapshot' => ['campus_ids' => [$campus->id]],
        'permission_result' => 'allowed',
    ], [
        'summary' => ['student_count' => 2],
        'groups' => [['key' => 'program', 'value' => 'IT']],
        'source_references' => [[
            'source_report' => 'finance.reporting.collection-progress',
            'source_reference_policy' => 'report_summary_with_filters',
        ]],
        'record_count' => 2,
        'freshness' => ['as_of' => '2026-06-30'],
    ]);

    $started = microtime(true) - 0.05;

    $row = app(McpAuditRecorder::class)->record(
        actor: $actor,
        mcpClientId: 'client-abc-123',
        result: $result,
        arguments: ['metric' => 'finance_collection_summary', 'campus_id' => $campus->id],
        startedAt: $started,
    );

    expect($row)->toBeInstanceOf(AiToolCall::class)
        ->and($row->channel)->toBe(AiToolCall::CHANNEL_MCP)
        ->and($row->ai_conversation_id)->toBeNull()
        ->and($row->ai_agent_trace_id)->toBeNull()
        ->and($row->actor_user_id)->toBe($actor->id)
        ->and($row->mcp_client_id)->toBe('client-abc-123')
        ->and($row->tool_name)->toBe('query_metrics')
        ->and($row->tool_schema_version)->toBe('query_metrics:v1')
        ->and($row->permission_result)->toBe('allowed')
        ->and($row->record_count)->toBe(2)
        ->and($row->status)->toBe('completed')
        ->and($row->safe_error_code)->toBeNull()
        ->and($row->duration_ms)->toBeGreaterThanOrEqual(0)
        ->and($row->campus_scope_snapshot)->toBe(['campus_ids' => [$campus->id]])
        ->and($row->source_references[0]['source_report'])->toBe('finance.reporting.collection-progress');

    // The actor relation resolves; the MCP scope finds the row.
    expect($row->actor->is($actor))->toBeTrue()
        ->and(AiToolCall::query()->mcp()->count())->toBe(1);
});

it('maps a denied result to its safe error code and redacts the audit arguments', function () {
    $actor = User::factory()->create();
    $campus = Campus::factory()->create();
    $secret = 'sk-live-mcp-secret';

    $result = QueryMetricsResult::denied('query_metrics', [
        'tool_schema_version' => 'query_metrics:v1',
        'catalog_version' => 'metric-catalog:v1',
        'metric' => 'finance_collection_summary',
        'campus_scope_snapshot' => ['campus_ids' => [$campus->id]],
        'permission_result' => 'allowed',
    ], 'forbidden_by_campus_scope');

    $row = app(McpAuditRecorder::class)->record(
        actor: $actor,
        mcpClientId: 'client-xyz',
        result: $result,
        arguments: ['api_key' => $secret, 'metric' => 'finance_collection_summary'],
        startedAt: microtime(true),
    );

    expect($row->status)->toBe('denied')
        ->and($row->safe_error_code)->toBe('forbidden_by_campus_scope')
        ->and($row->record_count)->toBe(0)
        ->and($row->channel)->toBe(AiToolCall::CHANNEL_MCP);

    expect(json_encode($row->redacted_arguments))->not->toContain($secret);
});

it('does not disturb existing chat-channel rows', function () {
    $actor = User::factory()->create();

    $chatRow = AiToolCall::query()->create([
        'ai_conversation_id' => null,
        'ai_agent_trace_id' => null,
        'tool_name' => 'query_metrics',
        'status' => 'completed',
        'record_count' => 1,
    ]);

    // The channel column defaults to chat for rows written without it.
    expect($chatRow->fresh()->channel)->toBe(AiToolCall::CHANNEL_CHAT)
        ->and(AiToolCall::query()->mcp()->count())->toBe(0);
});
