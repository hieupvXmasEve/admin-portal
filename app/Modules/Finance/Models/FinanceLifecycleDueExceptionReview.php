<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionReason;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinanceLifecycleDueExceptionReview extends Model
{
    protected $fillable = [
        'dng_payment_request_id',
        'student_id',
        'exception_reason',
        'status',
        'resolution_action',
        'resolution_reason',
        'resolved_at',
        'resolved_by_user_id',
        'last_seen_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'exception_reason' => LifecycleDueExceptionReason::class,
            'status' => LifecycleDueExceptionReviewStatus::class,
            'resolution_action' => LifecycleDueExceptionResolutionAction::class,
            'resolved_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function dngPaymentRequest(): BelongsTo
    {
        return $this->belongsTo(DngPaymentRequest::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FinanceLifecycleDueExceptionReviewEvent::class, 'finance_lifecycle_due_exception_review_id');
    }
}
