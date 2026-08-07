<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EmailLog extends AuditableModel
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'recipient',
        'sender',
        'subject',
        'status',
        'queued_at',
        'sent_at',
        'delivered_at',
        'failed_at',
        'error_message',
        'retry_count',
        'metadata',
        'message_id',
        'batch_id',
        'user_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'metadata' => 'array',
        'retry_count' => 'integer',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    /**
     * Email status constants
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT = 'sent';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_BOUNCED = 'bounced';
    public const STATUS_REJECTED = 'rejected';

    /**
     * Get all available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_QUEUED => 'Queued',
            self::STATUS_SENDING => 'Sending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_DELIVERED => 'Delivered',
            self::STATUS_FAILED => 'Failed',
            self::STATUS_BOUNCED => 'Bounced',
            self::STATUS_REJECTED => 'Rejected',
        ];
    }

    /**
     * User relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Mark email as queued
     */
    public function markAsQueued(): bool
    {
        return $this->update([
            'status' => self::STATUS_QUEUED,
            'queued_at' => now(),
        ]);
    }

    /**
     * Mark email as sending
     */
    public function markAsSending(): bool
    {
        return $this->update([
            'status' => self::STATUS_SENDING,
        ]);
    }

    /**
     * Mark email as sent
     */
    public function markAsSent(?string $messageId = null): bool
    {
        return $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'message_id' => $messageId,
        ]);
    }

    /**
     * Mark email as delivered
     */
    public function markAsDelivered(): bool
    {
        return $this->update([
            'status' => self::STATUS_DELIVERED,
            'delivered_at' => now(),
        ]);
    }

    /**
     * Mark email as failed
     */
    public function markAsFailed(string $errorMessage): bool
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'failed_at' => now(),
            'error_message' => $errorMessage,
            'retry_count' => $this->retry_count + 1,
        ]);
    }

    /**
     * Mark email as bounced
     */
    public function markAsBounced(string $reason): bool
    {
        return $this->update([
            'status' => self::STATUS_BOUNCED,
            'failed_at' => now(),
            'error_message' => $reason,
        ]);
    }

    /**
     * Mark email as rejected
     */
    public function markAsRejected(string $reason): bool
    {
        return $this->update([
            'status' => self::STATUS_REJECTED,
            'failed_at' => now(),
            'error_message' => $reason,
        ]);
    }

    /**
     * Check if email can be retried
     */
    public function canRetry(int $maxRetries = 3): bool
    {
        return $this->status === self::STATUS_FAILED && $this->retry_count < $maxRetries;
    }

    /**
     * Check if email is in a final state
     */
    public function isFinalState(): bool
    {
        return in_array($this->status, [
            self::STATUS_DELIVERED,
            self::STATUS_BOUNCED,
            self::STATUS_REJECTED,
        ]);
    }

    /**
     * Get delivery time in seconds
     */
    public function getDeliveryTimeAttribute(): ?int
    {
        if ($this->sent_at && $this->delivered_at) {
            return $this->delivered_at->diffInSeconds($this->sent_at);
        }

        return null;
    }

    /**
     * Scope for failed emails
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope for successful emails
     */
    public function scopeSuccessful($query)
    {
        return $query->whereIn('status', [self::STATUS_SENT, self::STATUS_DELIVERED]);
    }

    /**
     * Scope for emails by batch
     */
    public function scopeByBatch($query, string $batchId)
    {
        return $query->where('batch_id', $batchId);
    }

    /**
     * Scope for emails by date range
     */
    public function scopeByDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Get email statistics
     */
    public static function getStatistics(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $query = static::query();

        if ($startDate && $endDate) {
            $query->byDateRange($startDate, $endDate);
        }

        $total = $query->count();
        $sent = $query->clone()->successful()->count();
        $failed = $query->clone()->failed()->count();
        $pending = $query->clone()->whereIn('status', [
            self::STATUS_PENDING,
            self::STATUS_QUEUED,
            self::STATUS_SENDING,
        ])->count();

        return [
            'total' => $total,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Custom activity descriptions for email log events
     */
    public function getDescriptionForEvent(string $eventName): string
    {
        return match ($eventName) {
            'created' => "Email logged: {$this->subject} to {$this->recipient}",
            'updated' => "Email status updated: {$this->subject} to {$this->recipient} - {$this->status}",
            'deleted' => "Email log deleted: {$this->subject} to {$this->recipient}",
            default => "{$eventName} email log: {$this->subject} to {$this->recipient}",
        };
    }
}
