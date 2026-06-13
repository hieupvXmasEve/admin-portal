<?php

declare(strict_types=1);

namespace App\Modules\Finance\Models;

use App\Models\Student;
use App\Models\User;
use App\Modules\Finance\Dng\Models\DngPaymentRequest;
use App\Modules\Finance\Enums\LifecycleDueExceptionResolutionAction;
use App\Modules\Finance\Enums\LifecycleDueExceptionReviewEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinanceLifecycleDueExceptionReviewEvent extends Model
{
    protected $fillable = [
        'finance_lifecycle_due_exception_review_id',
        'dng_payment_request_id',
        'student_id',
        'event_type',
        'from_status',
        'to_status',
        'resolution_action',
        'resolution_reason',
        'performed_by_user_id',
        'performed_at',
        'dng_status_before',
        'dng_status_after',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => LifecycleDueExceptionReviewEventType::class,
            'resolution_action' => LifecycleDueExceptionResolutionAction::class,
            'performed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(FinanceLifecycleDueExceptionReview::class, 'finance_lifecycle_due_exception_review_id');
    }

    public function dngPaymentRequest(): BelongsTo
    {
        return $this->belongsTo(DngPaymentRequest::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function performedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by_user_id');
    }
}
