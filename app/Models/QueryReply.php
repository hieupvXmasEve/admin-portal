<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QueryReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'ticket_id',
        'author_user_id',
        'message',
        'is_official_answer',
        'attachment_id',
    ];

    protected $casts = [
        'is_official_answer' => 'boolean',
    ];

    /**
     * Get the ticket that owns the reply.
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(QueryTicket::class, 'ticket_id');
    }

    /**
     * Get the author of the reply.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    /**
     * Get the attachment for the reply.
     */
    public function attachment(): BelongsTo
    {
        return $this->belongsTo(Attachment::class);
    }

    /**
     * Scope to get official answers.
     */
    public function scopeOfficialAnswers($query)
    {
        return $query->where('is_official_answer', true);
    }

    /**
     * Scope to get replies ordered by creation date.
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('created_at');
    }
}
