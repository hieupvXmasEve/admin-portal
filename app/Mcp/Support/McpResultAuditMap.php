<?php

declare(strict_types=1);

namespace App\Mcp\Support;

/**
 * FROZEN CONTRACT (Issue 01 spike → consumed by Issue 03 recorder + Step 4 wrapper).
 *
 * Documents the stable mapping the {@see McpAuditRecorder} reads off the dispatcher
 * result objects (`QueryMetricsResult|EntitySearchResult|EntityProfileResult`) when it
 * writes one standalone `ai_tool_calls` row per MCP call. Every accessor below is a
 * public method (or `toArray()` key) shared by all three result objects, so the
 * recorder can stay result-type agnostic.
 *
 * This is documentation-as-code: a single source of truth for the audit field map so
 * Step 2's `campus_scope_snapshot` changes and Step 3's recorder code against one shape.
 */
final class McpResultAuditMap
{
    /**
     * Audit column => the result accessor / snapshot key it is sourced from.
     *
     * Columns redacted via AiRedactor before storage (audit fields only — there is no
     * egress redaction of the tool output, ADR-0011): redacted_arguments,
     * campus_scope_snapshot, hidden_sections, source_references, redacted_result_summary.
     *
     * @var array<string, string>
     */
    public const FIELD_MAP = [
        'tool_name' => "toArray()['tool']",
        'tool_schema_version' => "toArray()['tool_schema_version']",
        'permission_result' => 'permissionResult()',
        'campus_scope_snapshot' => "toArray()['campus_scope_snapshot'] (CampusScopeSnapshot shape)",
        'hidden_sections' => "toArray()['hidden_sections']",
        'record_count' => 'recordCount()',
        'source_references' => 'sourceReferences()',
        'redacted_result_summary' => 'auditSummary()',
        'status' => 'status()',
        'safe_error_code' => 'safeErrorCode()',
    ];

    /**
     * Identity columns set by the recorder from the MCP call context (not the result):
     * channel = 'mcp', actor_user_id = resolved actor, mcp_client_id = OAuth client id,
     * ai_conversation_id = null, ai_agent_trace_id = null (no chat run over MCP).
     */
    public const CHANNEL = 'mcp';
}
