<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceCancellationCompletionOutbox extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_DISPATCHED = 'dispatched';

    protected $table = 'finance_cancellation_completion_outbox';

    protected $fillable = ['finance_cancellation_operation_id', 'event_id', 'status', 'dispatched_at'];

    protected function casts(): array
    {
        return ['dispatched_at' => 'datetime'];
    }

    public function operation(): BelongsTo
    {
        return $this->belongsTo(FinanceCancellationOperation::class, 'finance_cancellation_operation_id');
    }
}
