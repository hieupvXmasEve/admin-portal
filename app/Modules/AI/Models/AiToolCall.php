<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolCall extends Model
{
    /**
     * In-house Staff Copilot chat channel (the historical default).
     */
    public const CHANNEL_CHAT = 'chat';

    /**
     * Controlled MCP server channel (ADR-0008/0009) — standalone rows, no chat run.
     */
    public const CHANNEL_MCP = 'mcp';

    protected $fillable = [
        'ai_conversation_id',
        'ai_agent_trace_id',
        'channel',
        'actor_user_id',
        'mcp_client_id',
        'tool_name',
        'tool_schema_version',
        'redacted_arguments',
        'permission_result',
        'campus_scope_snapshot',
        'hidden_sections',
        'record_count',
        'source_references',
        'redacted_result_summary',
        'status',
        'duration_ms',
        'safe_error_code',
    ];

    protected function casts(): array
    {
        return [
            'redacted_arguments' => 'array',
            'campus_scope_snapshot' => 'array',
            'hidden_sections' => 'array',
            'record_count' => 'integer',
            'source_references' => 'array',
            'redacted_result_summary' => 'array',
            'duration_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function trace(): BelongsTo
    {
        return $this->belongsTo(AiAgentTrace::class, 'ai_agent_trace_id');
    }

    /**
     * The staff member an MCP tool call was impersonated for (ADR-0010).
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Limit the query to MCP-channel tool calls.
     */
    public function scopeMcp(Builder $query): Builder
    {
        return $query->where('channel', self::CHANNEL_MCP);
    }
}
