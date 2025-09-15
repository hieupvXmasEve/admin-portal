<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QueryTicket extends Model
{
    use HasFactory;

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
        return $this->hasMany(QueryReply::class, 'ticket_id')->orderBy('created_at');
    }

    /**
     * Close the ticket.
     */
    public function close(): void
    {
        $this->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);
    }

    /**
     * Reopen the ticket.
     */
    public function reopen(): void
    {
        $this->update([
            'status' => 'open',
            'closed_at' => null,
        ]);
    }

    /**
     * Mark as answered.
     */
    public function markAsAnswered(): void
    {
        $this->update(['status' => 'answered']);
    }

    /**
     * Assign to a user.
     */
    public function assignTo(User $user): void
    {
        $this->update(['assigned_to_user_id' => $user->id]);
    }
}