<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiAgentTrace extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'ai_message_id',
        'provider',
        'model',
        'prompt_version',
        'catalog_version',
        'tool_schema_version',
        'status',
        'step_count',
        'duration_ms',
        'safe_error_code',
        'final_answer_id',
    ];

    protected function casts(): array
    {
        return [
            'step_count' => 'integer',
            'duration_ms' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'ai_message_id');
    }

    public function toolCalls(): HasMany
    {
        return $this->hasMany(AiToolCall::class);
    }

    public function providerUsages(): HasMany
    {
        return $this->hasMany(AiProviderUsage::class);
    }
}
