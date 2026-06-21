<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiToolCall extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'ai_agent_trace_id',
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
}
