<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiRunEvent extends Model
{
    protected $fillable = [
        'ai_chat_run_id',
        'ai_conversation_id',
        'ai_message_id',
        'ai_tool_call_id',
        'event_type',
        'sequence',
        'redacted_payload',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'redacted_payload' => 'array',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AiChatRun::class, 'ai_chat_run_id');
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    public function toolCall(): BelongsTo
    {
        return $this->belongsTo(AiToolCall::class, 'ai_tool_call_id');
    }
}
