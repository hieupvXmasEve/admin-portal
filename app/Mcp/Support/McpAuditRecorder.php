<?php

declare(strict_types=1);

namespace App\Mcp\Support;

use App\Models\User;
use App\Modules\AI\Models\AiToolCall;
use App\Modules\AI\Support\AiAuditRecorder;
use App\Modules\AI\Support\AiRedactor;
use App\Modules\AI\Support\Tools\EntityProfileResult;
use App\Modules\AI\Support\Tools\EntitySearchResult;
use App\Modules\AI\Support\Tools\QueryMetricsResult;

/**
 * Writes one standalone audit row per Controlled MCP server tool call (ADR-0008/0009).
 *
 * Unlike {@see AiAuditRecorder::recordToolCall()}, which requires
 * a non-null agent trace and is the chat channel's recorder, this recorder persists an
 * MCP call with no conversation/trace linkage — channel = `mcp`, the impersonated actor,
 * and the OAuth client id — by reading the dispatcher result object's frozen accessors.
 *
 * Only the audit fields are redacted (via {@see AiRedactor}); there is no egress redaction
 * of the tool output returned to the client (ADR-0011).
 */
class McpAuditRecorder
{
    public function __construct(private readonly AiRedactor $redactor) {}

    /**
     * Persist a single MCP-channel tool call.
     *
     * @param  array<string, mixed>  $arguments  the raw tool arguments (redacted before storage)
     * @param  float  $startedAt  the `microtime(true)` timestamp captured before dispatch
     */
    public function record(
        User $actor,
        string $mcpClientId,
        QueryMetricsResult|EntitySearchResult|EntityProfileResult $result,
        array $arguments,
        float $startedAt,
    ): AiToolCall {
        $snapshot = $result->toArray();

        return AiToolCall::query()->create([
            'ai_conversation_id' => null,
            'ai_agent_trace_id' => null,
            'channel' => AiToolCall::CHANNEL_MCP,
            'actor_user_id' => $actor->id,
            'mcp_client_id' => $mcpClientId,
            'tool_name' => (string) ($snapshot['tool'] ?? ''),
            'tool_schema_version' => $this->nullableString($snapshot['tool_schema_version'] ?? null),
            'redacted_arguments' => $this->redactor->redact($arguments),
            'permission_result' => $result->permissionResult(),
            'campus_scope_snapshot' => $this->redactor->redact($snapshot['campus_scope_snapshot'] ?? []),
            'hidden_sections' => $this->redactor->redact($snapshot['hidden_sections'] ?? []),
            'record_count' => $result->recordCount(),
            'source_references' => $this->redactor->redact($result->sourceReferences()),
            'redacted_result_summary' => $this->redactor->redact($result->auditSummary()),
            'status' => $result->status(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'safe_error_code' => $this->nullableString($result->safeErrorCode()),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }
}
