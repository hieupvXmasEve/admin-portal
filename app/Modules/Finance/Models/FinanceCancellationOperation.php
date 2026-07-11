<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FinanceCancellationOperation extends Model
{
    public const STATUS_REQUESTED = 'requested';

    /** Claimed by a processor worker; provider work may be in flight. */
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_REQUIRES_REVIEW = 'requires_review';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'source_system', 'source_kind', 'source_ref', 'obligation_type', 'status',
        'unpaid_void_reason', 'paid_void_reason', 'actor_user_id', 'source_payload',
        'result_payload', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'source_payload' => 'array',
            'result_payload' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function completionOutbox(): HasOne
    {
        return $this->hasOne(FinanceCancellationCompletionOutbox::class);
    }
}
