<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiProviderUsage extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'ai_agent_trace_id',
        'ai_message_id',
        'provider',
        'model',
        'status',
        'tokens_in',
        'tokens_out',
        'estimated_cost_minor',
        'duration_ms',
        'safe_error_code',
        'provider_request_id',
    ];

    protected function casts(): array
    {
        return [
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'estimated_cost_minor' => 'integer',
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

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }
}
