<?php

declare(strict_types=1);

namespace App\Modules\AI\Models;

use App\Models\Campus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiChatRun extends Model
{
    private const NON_RETRYABLE_SAFE_ERROR_CODES = [
        'unsupported_staff_question',
    ];

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_PLANNING = 'planning';

    public const STATUS_TOOL_RUNNING = 'tool_running';

    public const STATUS_STREAMING = 'streaming';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'ai_conversation_id',
        'user_message_id',
        'assistant_message_id',
        'ai_agent_trace_id',
        'user_id',
        'campus_id',
        'provider',
        'model',
        'prompt_version',
        'catalog_version',
        'tool_schema_version',
        'runtime_mode',
        'stream_transport',
        'stream_mode',
        'status',
        'cancellation_requested_at',
        'started_at',
        'completed_at',
        'failed_at',
        'duration_ms',
        'safe_error_code',
        'last_event_id',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'cancellation_requested_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'failed_at' => 'datetime',
            'duration_ms' => 'integer',
            'last_event_id' => 'integer',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function userMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'user_message_id');
    }

    public function assistantMessage(): BelongsTo
    {
        return $this->belongsTo(AiMessage::class, 'assistant_message_id');
    }

    public function trace(): BelongsTo
    {
        return $this->belongsTo(AiAgentTrace::class, 'ai_agent_trace_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function campus(): BelongsTo
    {
        return $this->belongsTo(Campus::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AiRunEvent::class, 'ai_chat_run_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_FAILED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function isCancellable(): bool
    {
        return ! $this->isTerminal();
    }

    public function isRetryable(): bool
    {
        if ($this->status !== self::STATUS_FAILED) {
            return false;
        }

        if ($this->safe_error_code === null) {
            return true;
        }

        return ! in_array($this->safe_error_code, self::NON_RETRYABLE_SAFE_ERROR_CODES, true);
    }
}
