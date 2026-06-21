<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiConversation extends Model
{
    protected $fillable = [
        'user_id',
        'campus_id',
        'origin',
        'title',
        'status',
        'request_id',
        'actor_role_snapshot',
        'campus_scope_snapshot',
        'sdk_conversation_id',
        'last_message_at',
    ];

    protected function casts(): array
    {
        return [
            'actor_role_snapshot' => 'array',
            'campus_scope_snapshot' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class);
    }

    public function traces(): HasMany
    {
        return $this->hasMany(AiAgentTrace::class);
    }
}
