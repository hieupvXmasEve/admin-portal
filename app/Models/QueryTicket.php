<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueryTicket extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_ANSWERED = 'answered';
    public const STATUS_CLOSED = 'closed';

    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_PENDING,
        self::STATUS_ANSWERED,
        self::STATUS_CLOSED,
    ];

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';

    protected $table = 'queries_tickets';

    protected $fillable = [
        'response_id',
        'topic_id',
        'custom_topic_text',
        'status',
        'priority',
        'assigned_to_user_id',
        'closed_at',
    ];

    protected $casts = [
        'closed_at' => 'datetime',
        'status' => 'string',
        'priority' => 'string',
    ];

    /**
     * Get the response that owns the ticket.
     */
    public function response(): BelongsTo
    {
        return $this->belongsTo(FormResponse::class, 'response_id');
    }

    /**
     * Get the topic for the ticket.
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(QueryTopic::class, 'topic_id');
    }

    /**
     * Get the user assigned to the ticket.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    /**
     * Get the replies for the ticket.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(QueryReply::class, 'ticket_id')->orderByDesc('created_at');
    }

    /**
     * Get the assignment audit history for this ticket.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(QueryAssignment::class, 'query_ticket_id');
    }

    /**
     * Close the ticket.
     */
    public function close(): void
    {
        $this->update([
            'status' => self::STATUS_CLOSED,
            'closed_at' => now(),
        ]);
    }

    /**
     * Reopen the ticket.
     */
    public function reopen(): void
    {
        $this->update([
            'status' => self::STATUS_OPEN,
            'closed_at' => null,
        ]);
    }

    /**
     * Mark as answered.
     */
    public function markAsAnswered(): void
    {
        $this->update([
            'status' => self::STATUS_ANSWERED,
            'closed_at' => null,
        ]);
    }

    /**
     * Assign to a user.
     */
    public function assignTo(User $user): void
    {
        $this->update(['assigned_to_user_id' => $user->id]);
    }
}
