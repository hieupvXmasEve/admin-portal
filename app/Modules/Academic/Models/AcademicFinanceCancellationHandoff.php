<?php

declare(strict_types=1);

namespace App\Modules\Academic\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Academic-owned durable handoff for Finance Cancellation Operation requests.
 * Written in the same transaction that marks the source Finance-Pending so a
 * crash cannot leave pending without a recoverable Finance request intent.
 */
class AcademicFinanceCancellationHandoff extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHED = 'dispatched';

    public const STATUS_FAILED = 'failed';

    protected $table = 'academic_finance_cancellation_handoffs';

    protected $fillable = [
        'source_system',
        'source_kind',
        'source_ref',
        'obligation_type',
        'unpaid_void_reason',
        'paid_void_reason',
        'actor_user_id',
        'payload',
        'status',
        'attempts',
        'last_error',
        'dispatched_at',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'dispatched_at' => 'datetime',
        ];
    }
}
