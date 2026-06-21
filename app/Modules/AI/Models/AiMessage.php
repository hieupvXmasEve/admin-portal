<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiMessage extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'role',
        'redacted_content',
        'content_hash',
        'content_classification',
        'final_answer_id',
        'hidden_sections',
    ];

    protected function casts(): array
    {
        return [
            'hidden_sections' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function traces(): HasMany
    {
        return $this->hasMany(AiAgentTrace::class);
    }
}
